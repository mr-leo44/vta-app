<?php

namespace App\Enums;

enum FlightStatusEnum: string
{
    case QRF = 'qrf';
    case SCHEDULED = 'prevu';
    case DEPARTED = 'embarque';
    case CANCELLED = 'annule';
    case DIVERTED = 'detourne';

    public function label(): string
    {
        return match($this) {
            self::QRF => 'QRF',
            self::SCHEDULED => 'Vol prévu',
            self::DEPARTED => 'Vol atterri',
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
