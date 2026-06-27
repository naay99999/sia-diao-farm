<?php

namespace App\Enums;

enum CropCycleStage: string
{
    case SoilPrep = 'soil_prep';
    case Flowering = 'flowering';
    case Fruiting = 'fruiting';
    case ReadyToHarvest = 'ready_to_harvest';
    case Harvested = 'harvested';

    public function label(): string
    {
        return match ($this) {
            self::SoilPrep => 'บำรุงดิน',
            self::Flowering => 'ออกดอก',
            self::Fruiting => 'ติดผล',
            self::ReadyToHarvest => 'พร้อมเก็บเกี่ยว',
            self::Harvested => 'เก็บเกี่ยวแล้ว',
        };
    }
}
