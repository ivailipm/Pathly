<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Tags are typed as one comma-separated string and stored as a list: at most 5, each up to 24 characters.
 */
final class TagList implements ValidationRule
{
    public const MAX_TAGS = 5;

    public const MAX_LENGTH = 24;

    /**
     * "Backend, portfolio ,backend," → ['backend', 'portfolio']: trimmed, lowercase, without empties or repeats.
     *
     * @return list<string>
     */
    public static function parse(?string $value): array
    {
        $tags = array_map(fn (string $tag) => mb_strtolower(trim($tag)), explode(',', (string) $value));

        return array_values(array_unique(array_filter($tags, fn (string $tag) => $tag !== '')));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $tags = self::parse(is_string($value) ? $value : null);

        if (count($tags) > self::MAX_TAGS) {
            $fail(__('Use at most :count tags.', ['count' => self::MAX_TAGS]));

            return;
        }

        foreach ($tags as $tag) {
            if (mb_strlen($tag) > self::MAX_LENGTH) {
                $fail(__('Keep each tag to :count characters or fewer.', ['count' => self::MAX_LENGTH]));

                return;
            }
        }
    }
}
