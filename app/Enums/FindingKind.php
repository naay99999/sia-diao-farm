<?php

namespace App\Enums;

enum FindingKind: string
{
    case Problem = 'problem';
    case Stage = 'stage';

    public function label(): string
    {
        return match ($this) {
            self::Problem => 'ปัญหา',
            self::Stage => 'ระยะการเติบโต',
        };
    }
}
