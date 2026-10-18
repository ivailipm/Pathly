<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['milestone_id', 'title', 'url', 'status', 'position'])]
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
        ];
    }

    /**
     * @return BelongsTo<Milestone, $this>
     */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }
}
