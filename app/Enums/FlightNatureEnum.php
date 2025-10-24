<?php

namespace App\Enums;

enum FlightNatureEnum: string
{
    case COMMERCIAL = 'commercial';
    case NON_COMMERCIAL = 'non_commercial';

    public function label(): string
    {
        return match($this) {
            self::COMMERCIAL => 'Vol commercial',
            self::NON_COMMERCIAL => 'Vol non commercial',
        };
    }

    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn($case) => [$case->value => $case->label()])
            ->toArray();
    }
}
