<?php

namespace App\Enums;

enum ExpenseScope: string
{
    case Direct = 'direct';
    case Overhead = 'overhead';

    public function label(): string
    {
        return match ($this) {
            self::Direct => 'ต้นทุนตรง (ผูกรอบการผลิต)',
            self::Overhead => 'ค่าส่วนกลาง',
        };
    }
}
