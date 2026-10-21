<?php

namespace App\Enums;

enum CareType: string
{
    case Fertilizer = 'fertilizer';
    case Pesticide = 'pesticide';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Fertilizer => 'ใส่ปุ๋ย',
            self::Pesticide => 'ฉีดยา',
            self::Other => 'อื่น ๆ',
        };
    }
}
