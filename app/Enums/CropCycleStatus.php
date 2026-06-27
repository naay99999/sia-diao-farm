<?php

namespace App\Enums;

enum CropCycleStatus: string
{
    case Active = 'active';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'กำลังดำเนินการ',
            self::Closed => 'ปิดรอบแล้ว',
        };
    }
}
