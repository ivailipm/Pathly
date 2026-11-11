<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Carbon\CarbonImmutable;
use Database\Factories\MilestoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $subject_id
 * @property string $title
 * @property string|null $description
 * @property int $position
 * @property CarbonImmutable|null $completed_at set by syncCompletion(), not fillable
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int $number only when loaded with withNumber()
 * @property-read int $tasks_count only when loaded with withTaskCounts()
 * @property-read int $done_tasks_count
 * @property-read int $in_progress_tasks_count
 * @property-read bool $is_done
 */
#[Fillable(['subject_id', 'title', 'description', 'position'])]
class Milestone extends Model
{
    /** @use HasFactory<MilestoneFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            // Counts come back as different types on SQLite and Postgres
            'number' => 'integer',
            'tasks_count' => 'integer',
            'done_tasks_count' => 'integer',
            'in_progress_tasks_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderBy('position');
    }

    /**
     * Field notes, newest first.
     *
     * @return HasMany<Note, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class)->latest()->latest('id');
    }

    /**
     * R2: add `number`, the milestone's 1-based place among all of its subject's milestones (position, then id).
     * It counts the earlier siblings in a subquery, so it stays right inside a filtered query.
     *
     * @param  Builder<Milestone>  $query
     */
    public function scopeWithNumber(Builder $query): void
    {
        $query->addSelect([
            'number' => DB::table('milestones', 'siblings')
                ->selectRaw('count(*)')
                ->whereColumn('siblings.subject_id', 'milestones.subject_id')
                ->where(fn (QueryBuilder $earlier) => $earlier
                    ->whereColumn('siblings.position', '<', 'milestones.position')
                    ->orWhere(fn (QueryBuilder $samePosition) => $samePosition
                        ->whereColumn('siblings.position', 'milestones.position')
                        ->whereColumn('siblings.id', '<=', 'milestones.id'))),
        ]);
    }

    /**
     * Add tasks_count, done_tasks_count and in_progress_tasks_count.
     *
     * @param  Builder<Milestone>  $query
     */
    public function scopeWithTaskCounts(Builder $query): void
    {
        $query->withCount([
            'tasks',
            'tasks as done_tasks_count' => fn ($tasks) => $tasks->where('status', TaskStatus::Done),
            'tasks as in_progress_tasks_count' => fn ($tasks) => $tasks->where('status', TaskStatus::InProgress),
        ]);
    }

    /**
     * R6: record when the milestone was finished. Sets completed_at when it has tasks and all are done (keeping an
     * earlier date), and clears it as soon as one task isn't done or no tasks are left. Called after every status
     * change on the board, not in factories or seeders.
     */
    public function syncCompletion(): void
    {
        // reorder() drops the relation's orderBy, which Postgres rejects in an aggregate
        $total = $this->tasks()->reorder()->count();
        $done = $this->tasks()->reorder()->where('status', TaskStatus::Done)->count();

        if ($total > 0 && $done === $total) {
            $this->completed_at ??= now();
        } else {
            $this->completed_at = null;
        }

        $this->save();
    }

    /**
     * Move a task to a column (its status) and a spot in it: $index counts the column's other tasks. The whole
     * milestone is renumbered 1..n in board order (columns in status order), then completion is synced (R6).
     */
    public function moveTask(Task $task, TaskStatus $status, int $index): void
    {
        DB::transaction(function () use ($task, $status, $index) {
            $others = $this->tasks()->whereKeyNot($task->id)->get();

            $columns = [];

            foreach (TaskStatus::cases() as $column) {
                $columns[$column->value] = $others->filter(fn (Task $other) => $other->status === $column)->values();
            }

            $task->status = $status;
            $columns[$status->value]->splice(max(0, min($index, $columns[$status->value]->count())), 0, [$task]);

            // save() skips tasks that didn't change
            $next = 1;

            foreach ($columns as $columnTasks) {
                foreach ($columnTasks as $columnTask) {
                    $columnTask->position = $next++;
                    $columnTask->save();
                }
            }

            $this->syncCompletion();
        });
    }

    /**
     * Move a task to the end of a column, like the "Move to" menu.
     */
    public function moveTaskToEnd(Task $task, TaskStatus $status): void
    {
        $this->moveTask($task, $status, PHP_INT_MAX);
    }

    /**
     * Delete a task. A timer running on it is stopped first, so its time stays on the subject. Returns false when
     * that timer had been forgotten (over the limit) and was discarded instead.
     */
    public function deleteTask(Task $task): bool
    {
        $kept = $task->studySessions()->running()->get()
            ->reduce(fn (bool $kept, StudySession $session) => $session->stop() && $kept, true);

        $task->delete();

        // Deleting the last task that wasn't done finishes the milestone; deleting the last task reopens it
        $this->syncCompletion();

        return $kept;
    }

    /**
     * R3: done once it has a completion date (R6). An empty milestone is never done.
     *
     * @return Attribute<bool, never>
     */
    protected function isDone(): Attribute
    {
        return Attribute::get(fn (): bool => $this->completed_at !== null);
    }
}
