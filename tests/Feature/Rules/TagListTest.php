<?php

use App\Rules\TagList;
use Illuminate\Support\Facades\Validator;

/**
 * @return list<string>
 */
function tagErrors(string $tags): array
{
    return Validator::make(['tags' => $tags], ['tags' => [new TagList]])->errors()->get('tags');
}

it('cleans tags into a unique lowercase list', function () {
    expect(TagList::parse(' Backend, portfolio ,BACKEND,, '))->toBe(['backend', 'portfolio'])
        ->and(TagList::parse(''))->toBe([])
        ->and(TagList::parse(null))->toBe([]);
});

it('accepts up to five tags of up to 24 characters', function () {
    expect(tagErrors('a, b, c, d, '.str_repeat('e', 24)))->toBe([]);
});

it('rejects more than five tags', function () {
    expect(tagErrors('a, b, c, d, e, f'))->toBe(['Use at most 5 tags.']);
});

it('counts repeated tags once', function () {
    expect(tagErrors('a, b, c, d, e, a, B'))->toBe([]);
});

it('rejects a tag over 24 characters', function () {
    expect(tagErrors('short, '.str_repeat('x', 25)))->toBe(['Keep each tag to 24 characters or fewer.']);
});
