<?php

namespace App\Rules;

use App\Support\Link;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Sources typed in the New subject form, one per line: a link, a title and a link, or just a title (a book).
 */
final class SourceList implements ValidationRule
{
    public const MAX_SOURCES = 20;

    /**
     * "Laravel docs https://laravel.com/docs" → title "Laravel docs" and that link; a bare link is named after its
     * site; a line without a link is a title only. Empty lines and repeated links are skipped.
     *
     * @return list<array{title: string, url: string|null}>
     */
    public static function parse(?string $value): array
    {
        $sources = [];
        $links = [];

        foreach (preg_split('/\R/u', (string) $value) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (preg_match('#https?://\S+#iu', $line, $match) !== 1) {
                $sources[] = ['title' => $line, 'url' => null];

                continue;
            }

            $url = $match[0];

            if (in_array($url, $links, true)) {
                continue;
            }

            // What's left around the link, without separators such as "Laravel docs – https://..."
            $title = (string) preg_replace('/^[\s\-–—:|·]+|[\s\-–—:|·]+$/u', '', str_replace($url, ' ', $line));

            $sources[] = ['title' => $title !== '' ? $title : Link::host($url), 'url' => $url];
            $links[] = $url;
        }

        return $sources;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $sources = self::parse(is_string($value) ? $value : null);

        if (count($sources) > self::MAX_SOURCES) {
            $fail(__('Add at most :count sources here; you can add more on the subject page.', ['count' => self::MAX_SOURCES]));

            return;
        }

        foreach ($sources as $source) {
            if ($source['url'] !== null && (mb_strlen($source['url']) > 2048 || filter_var($source['url'], FILTER_VALIDATE_URL) === false)) {
                $fail(__('":url" isn\'t a link we can use.', ['url' => str($source['url'])->limit(60)]));

                return;
            }

            if (mb_strlen($source['title']) > 255) {
                $fail(__('Keep each source title to 255 characters or fewer.'));

                return;
            }
        }
    }
}
