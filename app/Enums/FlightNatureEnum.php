<?php

namespace App\Enums;

enum FlightNatureEnum: string
{
    case COMMERCIAL = 'commercial';
    case STATE = 'state';
    case REQUISITION = 'requisition';
    case TEST = 'test';
    case AFREIGHTMENT = 'afreightment';
    case HUMANITARE = 'humanitare';

    public function label(): string
    {
        return match($this) {
            self::COMMERCIAL => 'Vol Commercial',
            self::STATE => 'Vol d\'Etat',
            self::REQUISITION => 'Vol de Requisition',
            self::AFREIGHTMENT => 'Vol d\'affretement',
            self::TEST => 'Vol de test',
            self::HUMANITARE => 'Vol Humanitaire',
        };
    }

    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn($case) => [$case->value => $case->label()])
            ->toArray();
    }
}
