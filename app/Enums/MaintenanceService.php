<?php

declare(strict_types=1);

namespace App\Enums;

enum MaintenanceService: string
{
    case OilChange = 'oil_change';
    case TireService = 'tire_service';
    case BrakeService = 'brake_service';
    case EngineService = 'engine_service';
    case BatteryService = 'battery_service';
    case Inspection = 'inspection';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::OilChange => 'Oil Change',
            self::TireService => 'Tire Service',
            self::BrakeService => 'Brake Service',
            self::EngineService => 'Engine Service',
            self::BatteryService => 'Battery Service',
            self::Inspection => 'Inspection',
            self::Other => 'Other',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::OilChange => 'تغيير الزيت',
            self::TireService => 'صيانة الإطارات',
            self::BrakeService => 'صيانة المكابح',
            self::EngineService => 'صيانة المحرك',
            self::BatteryService => 'صيانة البطارية',
            self::Inspection => 'فحص دوري',
            self::Other => 'أخرى',
        };
    }
}
