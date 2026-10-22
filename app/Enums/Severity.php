<?php

namespace App\Enums;

enum Severity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'น้อย',
            self::Medium => 'กลาง',
            self::High => 'มาก',
        };
    }
}
