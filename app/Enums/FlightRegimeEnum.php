<?php

namespace App\Enums;

enum FlightRegimeEnum: string
{
    case DOMESTIC = 'domestic';
    case INTERNATIONAL = 'international';

    public function label(): string
    {
        return match($this) {
            self::DOMESTIC => 'Vol domestique',
            self::INTERNATIONAL => 'Vol international',
        };
    }

    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn($case) => [$case->value => $case->label()])
            ->toArray();
    }
}
