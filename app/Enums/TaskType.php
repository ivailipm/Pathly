<?php

namespace App\Enums;

enum TaskType: string
{
    case Docs = 'docs';
    case Video = 'video';
    case Exercise = 'exercise';
    case Notebook = 'notebook';

    public function label(): string
    {
        return match ($this) {
            self::Docs => 'Docs',
            self::Video => 'Video',
            self::Exercise => 'Exercise',
            self::Notebook => 'Notebook',
        };
    }
}
