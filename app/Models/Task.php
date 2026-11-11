<?php

namespace App\Models;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Support\Link;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $milestone_id
 * @property string $title
 * @property string|null $url
 * @property string|null $description
 * @property TaskStatus $status
 * @property TaskType|null $type
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $display_url
 * @property-read int|null $minutes_logged only when loaded with withMinutesLogged(); null means none
 */
#[Fillable(['milestone_id', 'title', 'url', 'description', 'type', 'status', 'position'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /**
     * Default values for new tasks, matching the database defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => TaskStatus::Todo->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'type' => TaskType::class,
            'minutes_logged' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Milestone, $this>
     */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    /**
     * @return HasMany<StudySession, $this>
     */
    public function studySessions(): HasMany
    {
        return $this->hasMany(StudySession::class);
    }

    /**
     * The field notes linked to this task, newest first.
     *
     * @return HasMany<Note, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class)->latest()->latest('id');
    }

    /**
     * Add minutes_logged: the task's completed session minutes.
     *
     * @param  Builder<Task>  $query
     */
    public function scopeWithMinutesLogged(Builder $query): void
    {
        $query->withSum(['studySessions as minutes_logged' => fn ($sessions) => $sessions->completed()], 'minutes');
    }

    /**
     * The link without its scheme, "www." and trailing slash: "laravel.com/docs/reverb".
     *
     * @return Attribute<string|null, never>
     */
    protected function displayUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => Link::display($this->url));
    }
}
