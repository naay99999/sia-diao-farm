<?php

namespace App\Enums;

enum ExpenseCategory: string
{
    case Fertilizer = 'fertilizer';
    case Pesticide = 'pesticide';
    case Labor = 'labor';
    case Utilities = 'utilities';
    case Equipment = 'equipment';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Fertilizer => 'ปุ๋ย',
            self::Pesticide => 'ยา/สารเคมี',
            self::Labor => 'ค่าแรง',
            self::Utilities => 'น้ำ/ไฟ/น้ำมัน',
            self::Equipment => 'อุปกรณ์',
            self::Other => 'อื่น ๆ',
        };
    }
}
