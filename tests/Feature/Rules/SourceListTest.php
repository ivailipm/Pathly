<?php

use App\Rules\SourceList;
use Illuminate\Support\Facades\Validator;

/**
 * @return list<string>
 */
function sourceErrors(string $sources): array
{
    return Validator::make(['sources' => $sources], ['sources' => [new SourceList]])->errors()->get('sources');
}

it('reads a link, a title with a link, and a title on its own', function () {
    expect(SourceList::parse("https://www.laravel.com/docs\nLivewire docs – https://livewire.laravel.com/docs\n\n  Laravel: Up & Running  \r\nhttps://laracasts.com Laracasts"))
        ->toBe([
            ['title' => 'laravel.com', 'url' => 'https://www.laravel.com/docs'],
            ['title' => 'Livewire docs', 'url' => 'https://livewire.laravel.com/docs'],
            ['title' => 'Laravel: Up & Running', 'url' => null],
            ['title' => 'Laracasts', 'url' => 'https://laracasts.com'],
        ]);
});

it('skips empty input and repeated links', function () {
    expect(SourceList::parse(''))->toBe([])
        ->and(SourceList::parse(null))->toBe([])
        ->and(SourceList::parse("https://d3js.org\nD3 https://d3js.org"))->toBe([['title' => 'd3js.org', 'url' => 'https://d3js.org']]);
});

it('keeps a title with non-English letters intact', function () {
    expect(SourceList::parse('Gramática de uso del español — https://example.com/gramática'))
        ->toBe([['title' => 'Gramática de uso del español', 'url' => 'https://example.com/gramática']]);
});

it('treats a line without an http link as a title, so no other scheme becomes a link', function () {
    expect(SourceList::parse('javascript:alert(1)'))->toBe([['title' => 'javascript:alert(1)', 'url' => null]]);
});

it('accepts up to twenty sources', function () {
    expect(sourceErrors(implode("\n", array_map(fn ($i) => "Book {$i}", range(1, 20)))))->toBe([]);
});

it('rejects more than twenty sources', function () {
    expect(sourceErrors(implode("\n", array_map(fn ($i) => "Book {$i}", range(1, 21)))))
        ->toBe(['Add at most 20 sources here; you can add more on the subject page.']);
});

it('rejects a link that is not valid', function () {
    expect(sourceErrors('Docs https://exa..mple.com/'))->toBe(['"https://exa..mple.com/" isn\'t a link we can use.']);
});

it('rejects a title over 255 characters', function () {
    expect(sourceErrors(str_repeat('x', 256)))->toBe(['Keep each source title to 255 characters or fewer.']);
});
