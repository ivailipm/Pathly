<?php

namespace App\Models;

use App\Enums\SubjectColour;
use App\Enums\SubjectStatus;
use App\Enums\TaskStatus;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string|null $description
 * @property SubjectStatus $status
 * @property CarbonImmutable|null $target_date a date, never converted between timezones (R1)
 * @property list<string> $tags
 * @property SubjectColour $colour
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int $tasks_count only when loaded with withTaskCounts()
 * @property-read int $done_tasks_count
 * @property-read int $in_progress_tasks_count
 * @property-read int $milestones_count only when loaded with withLibraryData()
 * @property-read int $done_milestones_count
 * @property-read Milestone|null $current_milestone
 * @property-read bool $is_started
 * @property-read int|null $target_days_left positive = days left, 0 = due today, negative = days over
 * @property-read CarbonImmutable|null $last_studied_at only when loaded with withLibraryData()
 * @property-read int|null $days_since_studied 0 = today, 1 = yesterday, null = never (display timezone)
 */
#[Fillable(['title', 'description', 'status', 'target_date', 'tags', 'colour'])]
class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use HasFactory;

    /**
     * Default values for new subjects, matching the database defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'tags' => '[]',
        'colour' => 'clay',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubjectStatus::class,
            'target_date' => 'date',
            'tags' => 'array',
            'colour' => SubjectColour::class,
            'tasks_count' => 'integer',
            'done_tasks_count' => 'integer',
            'in_progress_tasks_count' => 'integer',
            'milestones_count' => 'integer',
            'last_studied_at' => 'datetime',
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
     * Ordered by position, then id (R2), so "the first milestone" is always the same one.
     *
     * @return HasMany<Milestone, $this>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasManyThrough<Task, Milestone, $this>
     */
    public function tasks(): HasManyThrough
    {
        return $this->hasManyThrough(Task::class, Milestone::class);
    }

    /**
     * Sessions by subject_id, so they keep counting after their task is deleted.
     *
     * @return HasMany<StudySession, $this>
     */
    public function studySessions(): HasMany
    {
        return $this->hasMany(StudySession::class);
    }

    /**
     * Add tasks_count, done_tasks_count and in_progress_tasks_count over all of the subject's milestones.
     *
     * @param  Builder<Subject>  $query
     */
    public function scopeWithTaskCounts(Builder $query): void
    {
        $query->withCount([
            'tasks',
            'tasks as done_tasks_count' => fn ($tasks) => $tasks->where('tasks.status', TaskStatus::Done),
            'tasks as in_progress_tasks_count' => fn ($tasks) => $tasks->where('tasks.status', TaskStatus::InProgress),
        ]);
    }

    /**
     * Everything the Library cards need, in a fixed number of queries: task counts, the milestone count,
     * and the milestones themselves (numbered, with their task counts) for current_milestone and
     * done_milestones_count.
     *
     * @param  Builder<Subject>  $query
     */
    public function scopeWithLibraryData(Builder $query): void
    {
        $query->withTaskCounts()
            ->withCount('milestones')
            ->withMax(['studySessions as last_studied_at' => fn ($sessions) => $sessions->completed()], 'started_at')
            ->with(['milestones' => fn ($milestones) => $milestones->withNumber()->withTaskCounts()]);
    }

    /**
     * R4: the first milestone that is not done, an empty one included. Reads the eager-loaded milestones.
     *
     * @return Attribute<Milestone|null, never>
     */
    protected function currentMilestone(): Attribute
    {
        return Attribute::get(fn (): ?Milestone => $this->milestones->first(fn (Milestone $milestone) => ! $milestone->is_done));
    }

    /**
     * R7: started once any of its tasks has left To do, or a session was logged on it.
     *
     * @return Attribute<bool, never>
     */
    protected function isStarted(): Attribute
    {
        return Attribute::get(fn (): bool => $this->done_tasks_count + $this->in_progress_tasks_count > 0
            || $this->last_studied_at !== null);
    }

    /**
     * Whole local days since the last completed session (R1): 0 = today, 1 = yesterday, null = never.
     *
     * @return Attribute<int|null, never>
     */
    protected function daysSinceStudied(): Attribute
    {
        return Attribute::get(function (): ?int {
            if ($this->last_studied_at === null) {
                return null;
            }

            // round(): days around a daylight-saving change are 23 or 25 hours long
            return (int) round(LocalTime::of($this->last_studied_at)->startOfDay()->diffInDays(LocalTime::today()));
        });
    }

    /**
     * Days until the target date in the display timezone (R1). The date itself is compared as stored.
     *
     * @return Attribute<int|null, never>
     */
    protected function targetDaysLeft(): Attribute
    {
        return Attribute::get(function (): ?int {
            if ($this->target_date === null) {
                return null;
            }

            $target = CarbonImmutable::parse($this->target_date->toDateString(), LocalTime::zone());

            // Carbon 3 returns a signed float
            return (int) round(LocalTime::today()->diffInDays($target, false));
        });
    }

    /**
     * @return Attribute<int<0, max>, never>
     */
    protected function doneMilestonesCount(): Attribute
    {
        return Attribute::get(fn (): int => $this->milestones->filter(fn (Milestone $milestone) => $milestone->is_done)->count());
    }
}
