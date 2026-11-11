<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\StudySessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Time studied on a subject, optionally on one of its tasks. A session is "completed" once it has minutes;
 * a running timer has none, and never counts in totals.
 *
 * @property int $id
 * @property int $user_id
 * @property int $subject_id
 * @property int|null $task_id
 * @property CarbonImmutable $started_at
 * @property int|null $minutes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['subject_id', 'task_id', 'started_at', 'minutes'])]
class StudySession extends Model
{
    /**
     * A timer running longer than this is treated as forgotten: discarded, not saved (spec assumption 11).
     */
    public const MAX_TIMER_MINUTES = 600;

    /** @use HasFactory<StudySessionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'minutes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Sessions with minutes: logged by hand, or a stopped timer.
     *
     * @param  Builder<StudySession>  $query
     */
    public function scopeCompleted(Builder $query): void
    {
        $query->whereNotNull('study_sessions.minutes');
    }

    /**
     * A timer that hasn't been stopped yet.
     *
     * @param  Builder<StudySession>  $query
     */
    public function scopeRunning(Builder $query): void
    {
        $query->whereNull('study_sessions.minutes');
    }

    /**
     * Stop a running timer and save its minutes (at least 1). A timer that ran over MAX_TIMER_MINUTES was
     * forgotten: it's deleted instead. Returns whether the time was saved.
     */
    public function stop(): bool
    {
        // Carbon 3 returns a signed float
        $minutes = (int) ceil(abs($this->started_at->diffInSeconds(now())) / 60);

        if ($minutes > self::MAX_TIMER_MINUTES) {
            $this->delete();

            return false;
        }

        $this->update(['minutes' => max(1, $minutes)]);

        return true;
    }
}
