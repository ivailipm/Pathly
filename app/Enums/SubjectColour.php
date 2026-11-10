<?php

namespace App\Enums;

enum SubjectColour: string
{
    case Clay = 'clay';
    case Slate = 'slate';
    case Moss = 'moss';
    case Plum = 'plum';

    public function label(): string
    {
        return match ($this) {
            self::Clay => 'Clay',
            self::Slate => 'Slate',
            self::Moss => 'Moss',
            self::Plum => 'Plum',
        };
    }

    /**
     * R8: the colours rotate in this order (clay, slate, moss, plum), so new subjects don't repeat the last one.
     */
    public static function forIndex(int $index): self
    {
        return self::cases()[$index % count(self::cases())];
    }
}
