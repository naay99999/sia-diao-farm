<?php

namespace App\Enums;

enum FollowUpOutcome: string
{
    case Resolved = 'resolved';
    case NotResolved = 'not_resolved';

    public function label(): string
    {
        return match ($this) {
            self::Resolved => 'หายแล้ว',
            self::NotResolved => 'ยังไม่หาย',
        };
    }
}
