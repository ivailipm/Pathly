<?php

namespace App\Support;

/**
 * A subject's colour, turned into the three tones the design uses: a soft background, readable text and the shade
 * of the large decorative letters and ruled lines.
 *
 * Any colour works. Only its hue and how colourful it is count: the lightness of each tone is fixed (measured from
 * the original four hand-picked tints in OKLCH), so every subject sits on paper the same way and its text always
 * stays readable on its background (at least 6:1, well above WCAG AA).
 */
final readonly class Tint
{
    /**
     * Ready-made colours, in the order new subjects rotate through (R8). Each is stored as its text colour.
     */
    public const PRESETS = [
        'Clay' => '#7a3b22',
        'Slate' => '#2d4b5f',
        'Moss' => '#4b5320',
        'Plum' => '#5b3a5b',
        'Ochre' => '#76510f',
        'Teal' => '#1d5955',
        'Rose' => '#7b3247',
        'Stone' => '#57524a',
    ];

    public const DEFAULT = self::PRESETS['Clay'];

    // OKLCH lightness of each tone, and the most colourful the text may get
    private const BACKGROUND_LIGHTNESS = 0.908;

    private const SHADE_LIGHTNESS = 0.852;

    private const INK_LIGHTNESS = 0.41;

    private const MAX_CHROMA = 0.10;

    // Background and shade are this much as colourful as the text
    private const BACKGROUND_CHROMA = 0.25;

    private const SHADE_CHROMA = 0.40;

    public function __construct(
        public string $background,
        public string $ink,
        public string $shade,
    ) {}

    /**
     * The tones for a "#rrggbb" colour. Anything else (it shouldn't be stored) gets the default colour.
     */
    public static function of(string $colour): self
    {
        /** @var array<string, self> $cache */
        static $cache = [];

        $colour = self::isColour($colour) ? strtolower($colour) : self::DEFAULT;

        if (isset($cache[$colour])) {
            return $cache[$colour];
        }

        [, $chroma, $hue] = self::toOklch($colour);
        $chroma = min($chroma, self::MAX_CHROMA);

        return $cache[$colour] = new self(
            background: self::toHex(self::BACKGROUND_LIGHTNESS, $chroma * self::BACKGROUND_CHROMA, $hue),
            ink: self::toHex(self::INK_LIGHTNESS, $chroma, $hue),
            shade: self::toHex(self::SHADE_LIGHTNESS, $chroma * self::SHADE_CHROMA, $hue),
        );
    }

    public static function isColour(mixed $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9a-f]{6}$/i', $value) === 1;
    }

    /**
     * R8: the preset for the user's n-th subject, so new subjects don't repeat the last colour.
     */
    public static function forIndex(int $index): string
    {
        $presets = array_values(self::PRESETS);

        return $presets[$index % count($presets)];
    }

    /**
     * The preset's name ("Clay"), or null for a custom colour.
     */
    public static function nameOf(string $colour): ?string
    {
        $name = array_search(strtolower($colour), self::PRESETS, true);

        return $name === false ? null : $name;
    }

    /**
     * @return array{0: float, 1: float, 2: float} lightness, chroma, hue in degrees
     */
    private static function toOklch(string $hex): array
    {
        [$r, $g, $b] = array_map(
            fn (string $channel) => self::toLinear(hexdec($channel) / 255),
            str_split(substr($hex, 1), 2),
        );

        $l = self::cubeRoot(0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b);
        $m = self::cubeRoot(0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b);
        $s = self::cubeRoot(0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b);

        $lightness = 0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s;
        $a = 1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s;
        $bAxis = 0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s;

        return [$lightness, hypot($a, $bAxis), fmod(rad2deg(atan2($bAxis, $a)) + 360, 360)];
    }

    /**
     * The OKLCH colour as "#rrggbb", lowering the chroma until it fits in sRGB.
     */
    private static function toHex(float $lightness, float $chroma, float $hue): string
    {
        $rgb = self::toLinearRgb($lightness, $chroma, $hue);

        if (! self::inGamut($rgb)) {
            [$low, $high] = [0.0, $chroma];

            for ($i = 0; $i < 30; $i++) {
                $mid = ($low + $high) / 2;
                self::inGamut(self::toLinearRgb($lightness, $mid, $hue)) ? $low = $mid : $high = $mid;
            }

            $rgb = self::toLinearRgb($lightness, $low, $hue);
        }

        return '#'.implode('', array_map(
            fn (float $channel) => sprintf('%02x', (int) round(self::toGamma(max(0.0, min(1.0, $channel))) * 255)),
            $rgb,
        ));
    }

    /**
     * @return array{0: float, 1: float, 2: float}
     */
    private static function toLinearRgb(float $lightness, float $chroma, float $hue): array
    {
        $a = $chroma * cos(deg2rad($hue));
        $b = $chroma * sin(deg2rad($hue));

        $l = ($lightness + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
        $m = ($lightness - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
        $s = ($lightness - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;

        return [
            4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s,
            -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s,
            -0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s,
        ];
    }

    /**
     * @param  array{0: float, 1: float, 2: float}  $rgb
     */
    private static function inGamut(array $rgb): bool
    {
        foreach ($rgb as $channel) {
            if ($channel < -0.000001 || $channel > 1.000001) {
                return false;
            }
        }

        return true;
    }

    private static function toLinear(float $channel): float
    {
        return $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
    }

    private static function toGamma(float $channel): float
    {
        return $channel <= 0.0031308 ? 12.92 * $channel : 1.055 * $channel ** (1 / 2.4) - 0.055;
    }

    private static function cubeRoot(float $value): float
    {
        return $value < 0 ? -((-$value) ** (1 / 3)) : $value ** (1 / 3);
    }
}
