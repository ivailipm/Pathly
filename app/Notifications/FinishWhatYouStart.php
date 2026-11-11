<?php

namespace App\Notifications;

use App\Models\Subject;
use App\Models\User;
use App\Support\LocalTime;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * The daily "finish what you start" reminder: one per user, listing the started subjects that went quiet.
 * Sent by the queued SendNudges job, so it isn't queued again itself.
 */
class FinishWhatYouStart extends Notification
{
    use Queueable;

    /**
     * Plain data, so the notification serializes the same way for mail and the database.
     *
     * @var list<array{id: int, title: string, days: int, milestone: string}>
     */
    public array $subjects;

    /**
     * @param  Collection<int, Subject>  $subjects  stalled subjects, loaded with withActivity()
     */
    public function __construct(Collection $subjects)
    {
        $this->subjects = array_values($subjects->map(fn (Subject $subject) => [
            'id' => $subject->id,
            'title' => $subject->title,
            'days' => $subject->last_activity_at === null ? 0 : LocalTime::daysSince($subject->last_activity_at),
            'milestone' => (string) $subject->current_milestone?->title,
        ])->all());
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('Finish what you start'))
            ->greeting(__('Hi :name,', ['name' => str($notifiable->name)->trim()->before(' ')]))
            ->line(trans_choice(
                'This subject has gone quiet:|These subjects have gone quiet:',
                count($this->subjects),
            ));

        foreach ($this->subjects as $subject) {
            $mail->line(__(':title: next up is ":milestone", :days', [
                'title' => $subject['title'],
                'milestone' => $subject['milestone'],
                'days' => trans_choice('quiet for :count day.|quiet for :count days.', $subject['days']),
            ]));
        }

        return $mail
            ->action(__('Pick up where you left off'), route('dashboard'))
            ->line(__('Ten minutes today keeps the path warm.'));
    }

    /**
     * Stored in the notifications table and shown on Today.
     *
     * @return array{subjects: list<array{id: int, title: string, days: int, milestone: string}>}
     */
    public function toArray(User $notifiable): array
    {
        return ['subjects' => $this->subjects];
    }
}
