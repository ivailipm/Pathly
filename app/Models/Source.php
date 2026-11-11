<?php

namespace App\Models;

use App\Support\Link;
use Carbon\CarbonImmutable;
use Database\Factories\SourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something a subject is learned from: docs, a course, a book. Reached through its subject (no policy of its own).
 *
 * @property int $id
 * @property int $subject_id
 * @property string $title
 * @property string|null $url null for a source without a link, such as a paper book
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read string|null $display_url
 */
#[Fillable(['title', 'url', 'position'])]
class Source extends Model
{
    /** @use HasFactory<SourceFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function displayUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => Link::display($this->url));
    }
}
