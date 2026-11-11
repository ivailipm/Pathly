<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Something on a milestone's board changed (a task moved, a timer started, a note was added...). Other open tabs
 * on that milestone reload the board. It carries only the id: listeners read the new state themselves, so
 * nothing private travels over the socket.
 *
 * Queued (ShouldBroadcast), so a queue worker must run for it to leave the app. Fire it with
 * broadcast(new BoardChanged($id))->toOthers(): dispatch() has no toOthers(), and the tab that made the change
 * already shows it.
 */
class BoardChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $milestoneId) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('milestones.'.$this->milestoneId)];
    }

    public function broadcastAs(): string
    {
        return 'board.changed';
    }
}
