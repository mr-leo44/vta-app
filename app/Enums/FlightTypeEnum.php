<?php

namespace App\Enums;

enum FlightTypeEnum: string
{
    case REGULAR = 'regular';
    case NON_REGULAR = 'non_regular';

    public function label(): string
    {
        return match($this) {
            self::REGULAR => 'Vol régulier',
            self::NON_REGULAR => 'Vol non régulier (VNR)',
        };
    }

    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn($case) => [$case->value => $case->label()])
            ->toArray();
    }
}
