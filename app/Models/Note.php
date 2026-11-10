<?php

namespace App\Models;

use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Database\Factories\NoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A field note: what clicked, what didn't, what to try next. Reached through its milestone (no policy of its own).
 *
 * @property int $id
 * @property int $milestone_id
 * @property int|null $task_id
 * @property string $body
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['task_id', 'body'])]
class Note extends Model
{
    /** @use HasFactory<NoteFactory> */
    use HasFactory;

    /**
     * Completed session minutes on the note's task, on the local day the note was written. Not a column:
     * set by attachMinutes(), and a display value only (two notes on the same task and day both show the total).
     */
    public int $minutes = 0;

    /**
     * @return BelongsTo<Milestone, $this>
     */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Set `minutes` on each note with one query: the completed sessions of the notes' tasks within the notes'
     * date range, grouped by task and local date in PHP (no SQL date functions, R1).
     *
     * @param  Collection<int, Note>  $notes
     */
    public static function attachMinutes(Collection $notes): void
    {
        $linked = $notes->filter(fn (Note $note) => $note->task_id !== null && $note->created_at !== null);

        if ($linked->isEmpty()) {
            return;
        }

        $dates = $linked->map(fn (Note $note) => LocalTime::dateOf($note->created_at));
        [$from] = LocalTime::utcRangeOfDay((string) $dates->min());
        [, $to] = LocalTime::utcRangeOfDay((string) $dates->max());

        $minutes = [];

        StudySession::query()
            ->completed()
            ->whereIn('task_id', $linked->pluck('task_id')->unique()->values())
            ->whereBetween('started_at', [$from, $to])
            ->get(['task_id', 'started_at', 'minutes'])
            ->each(function (StudySession $session) use (&$minutes) {
                $key = $session->task_id.'|'.LocalTime::dateOf($session->started_at);
                $minutes[$key] = ($minutes[$key] ?? 0) + (int) $session->minutes;
            });

        foreach ($linked as $note) {
            $note->minutes = $minutes[$note->task_id.'|'.LocalTime::dateOf($note->created_at)] ?? 0;
        }
    }
}
