<?php

use App\Support\Tint;

/**
 * WCAG contrast ratio of two "#rrggbb" colours.
 */
function contrastRatio(string $first, string $second): float
{
    $luminance = function (string $hex): float {
        [$r, $g, $b] = array_map(function (string $channel) {
            $value = hexdec($channel) / 255;

            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, str_split(substr($hex, 1), 2));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    };

    [$light, $dark] = [max($luminance($first), $luminance($second)), min($luminance($first), $luminance($second))];

    return ($light + 0.05) / ($dark + 0.05);
}

/**
 * How far apart two colours are, as the largest difference of one channel (0-255).
 */
function channelDistance(string $first, string $second): int
{
    $a = array_map('hexdec', str_split(substr($first, 1), 2));
    $b = array_map('hexdec', str_split(substr($second, 1), 2));

    return (int) max(array_map(fn ($x, $y) => abs($x - $y), $a, $b));
}

it('keeps the original four tints, within a few shades', function (string $name, string $background, string $ink, string $shade) {
    $tint = Tint::of(Tint::PRESETS[$name]);

    expect(channelDistance($tint->background, $background))->toBeLessThanOrEqual(6)
        ->and(channelDistance($tint->ink, $ink))->toBeLessThanOrEqual(6)
        ->and(channelDistance($tint->shade, $shade))->toBeLessThanOrEqual(10);
})->with([
    ['Clay', '#ebdcd2', '#7a3b22', '#dcc6b7'],
    ['Slate', '#d9e2e8', '#2d4b5f', '#c3d1da'],
    ['Moss', '#e2e5d3', '#4b5320', '#d0d5bc'],
    ['Plum', '#e7dde7', '#5b3a5b', '#d8c8d8'],
]);

it('keeps text readable on its background for any colour', function () {
    $worst = INF;

    foreach (range(0, 255, 51) as $r) {
        foreach (range(0, 255, 51) as $g) {
            foreach (range(0, 255, 51) as $b) {
                $tint = Tint::of(sprintf('#%02x%02x%02x', $r, $g, $b));
                $worst = min($worst, contrastRatio($tint->background, $tint->ink));
            }
        }
    }

    // WCAG AA for normal text is 4.5
    expect($worst)->toBeGreaterThan(6.0);
});

it('keeps the hue of the chosen colour', function () {
    $red = Tint::of('#ff0000');
    $blue = Tint::of('#0000ff');

    [$r, $g, $b] = array_map('hexdec', str_split(substr($red->ink, 1), 2));
    expect($r)->toBeGreaterThan($g)->toBeGreaterThan(0)->and($r)->toBeGreaterThan($b);

    [$r, $g, $b] = array_map('hexdec', str_split(substr($blue->ink, 1), 2));
    expect($b)->toBeGreaterThan($r)->and($b)->toBeGreaterThan($g);
});

it('makes white, black and grey neutral', function (string $colour) {
    $tint = Tint::of($colour);

    foreach ([$tint->background, $tint->ink, $tint->shade] as $tone) {
        [$r, $g, $b] = array_map('hexdec', str_split(substr($tone, 1), 2));
        expect(max($r, $g, $b) - min($r, $g, $b))->toBeLessThanOrEqual(1);
    }
})->with(['#ffffff', '#000000', '#808080']);

it('reads any case and falls back to the default for anything that is not #rrggbb', function () {
    expect(Tint::of('#E63946'))->toEqual(Tint::of('#e63946'))
        ->and(Tint::of('clay'))->toEqual(Tint::of(Tint::DEFAULT))
        ->and(Tint::of('#fff'))->toEqual(Tint::of(Tint::DEFAULT));
});

it('recognises #rrggbb colours only', function () {
    expect(Tint::isColour('#a1B2c3'))->toBeTrue()
        ->and(Tint::isColour('#abc'))->toBeFalse()
        ->and(Tint::isColour('red'))->toBeFalse()
        ->and(Tint::isColour(null))->toBeFalse();
});

it('names presets, not custom colours', function () {
    expect(Tint::nameOf('#7A3B22'))->toBe('Clay')
        ->and(Tint::nameOf('#e63946'))->toBeNull();
});

it('rotates new subjects through the presets (R8)', function (int $index, string $name) {
    expect(Tint::forIndex($index))->toBe(Tint::PRESETS[$name]);
})->with([
    [0, 'Clay'],
    [1, 'Slate'],
    [2, 'Moss'],
    [3, 'Plum'],
    [4, 'Ochre'],
    [7, 'Stone'],
    [8, 'Clay'],
]);
