<?php

namespace App\Support;

/**
 * How links are shown: tasks and sources show a link without its scheme, and a source without a title is named
 * after its site.
 */
final class Link
{
    /**
     * The link without its scheme, "www." and trailing slash: "laravel.com/docs/reverb".
     */
    public static function display(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = (string) preg_replace('#^https?://#i', '', $url);
        $url = (string) preg_replace('#^www\.#i', '', $url);

        return rtrim($url, '/');
    }

    /**
     * The site of a link, without "www.": "laravel.com". Falls back to the displayed link when it has no host.
     */
    public static function host(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return (string) self::display($url);
        }

        return (string) preg_replace('#^www\.#i', '', mb_strtolower($host));
    }
}
