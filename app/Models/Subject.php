<?php

namespace App\Models;

use App\Enums\SubjectStatus;
use App\Enums\TaskStatus;
use App\Support\LocalTime;
use App\Support\Tint;
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
 * @property string $colour "#rrggbb": any colour, turned into the subject's tones by Tint
 * @property-read Tint $tint
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
 * @property CarbonImmutable|null $nudged_at when a "finish what you start" reminder last mentioned it; not fillable
 * @property-read CarbonImmutable|null $last_task_change_at only when loaded with withActivity()
 * @property-read CarbonImmutable|null $last_activity_at the later of the last session and the last task change
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
        'colour' => Tint::DEFAULT,
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
            'tasks_count' => 'integer',
            'done_tasks_count' => 'integer',
            'in_progress_tasks_count' => 'integer',
            'milestones_count' => 'integer',
            'last_studied_at' => 'datetime',
            'last_task_change_at' => 'datetime',
            'nudged_at' => 'datetime',
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
     * @return HasManyThrough<Note, Milestone, $this>
     */
    public function notes(): HasManyThrough
    {
        return $this->hasManyThrough(Note::class, Milestone::class);
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
     * What the subject is learned from: links and books, in the order they were added.
     *
     * @return HasMany<Source, $this>
     */
    public function sources(): HasMany
    {
        return $this->hasMany(Source::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Add a source at the end of the list.
     */
    public function addSource(string $title, ?string $url): Source
    {
        return $this->sources()->create([
            'title' => $title,
            'url' => $url,
            // reorder() drops the relation's orderBy, which Postgres rejects in an aggregate
            'position' => (int) $this->sources()->reorder()->max('position') + 1,
        ]);
    }

    /**
     * Add a task's link to the sources, named after the task, unless it's already there. Returns whether it was added.
     */
    public function pinTaskLink(Task $task): bool
    {
        if ($task->url === null || $this->sources()->where('url', $task->url)->exists()) {
            return false;
        }

        $this->addSource($task->title, $task->url);

        return true;
    }

    /**
     * The background, text and shade colours of the subject's colour.
     *
     * @return Attribute<Tint, never>
     */
    protected function tint(): Attribute
    {
        return Attribute::get(fn (): Tint => Tint::of($this->colour));
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
        return Attribute::get(fn (): ?int => $this->last_studied_at === null ? null : LocalTime::daysSince($this->last_studied_at));
    }

    /**
     * The later of the last completed session and the last task change (a move, an edit, a new task).
     * Needs withActivity().
     *
     * @return Attribute<CarbonImmutable|null, never>
     */
    protected function lastActivityAt(): Attribute
    {
        return Attribute::get(function (): ?CarbonImmutable {
            $times = array_filter([$this->last_studied_at, $this->last_task_change_at]);

            return $times === [] ? null : max($times);
        });
    }

    /**
     * Everything isStalled() reads: the Library data plus the last task change.
     *
     * @param  Builder<Subject>  $query
     */
    public function scopeWithActivity(Builder $query): void
    {
        $query->withLibraryData()
            ->withMax('tasks as last_task_change_at', 'tasks.updated_at');
    }

    /**
     * "Finish what you start": an active subject that was started and isn't finished, has been quiet for at
     * least $days local days, and hasn't been mentioned in a reminder since it went quiet. Needs withActivity().
     */
    public function isStalled(int $days): bool
    {
        $lastActivity = $this->last_activity_at;

        return $this->status === SubjectStatus::Active
            && $this->is_started
            && $this->current_milestone !== null
            && $lastActivity !== null
            && LocalTime::daysSince($lastActivity) >= $days
            && ($this->nudged_at === null || $this->nudged_at->lessThan($lastActivity));
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
