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
}
