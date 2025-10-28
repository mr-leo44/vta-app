<?php

namespace App\Enums;

enum FlightStatusEnum: string
{
    case QRF = 'qrf';
    case SCHEDULED = 'prevu';
    case LANDED = 'atteri';
    case CANCELLED = 'annule';
    case DIVERTED = 'detourne';

    public function label(): string
    {
        return match($this) {
            self::QRF => 'QRF',
            self::SCHEDULED => 'Vol prévu',
            self::LANDED => 'Vol atterri',
            self::CANCELLED => 'Vol annulé',
            self::DIVERTED => 'Vol détourné',
        };
    }

    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn($case) => [$case->value => $case->label()])
            ->toArray();
    }
}
