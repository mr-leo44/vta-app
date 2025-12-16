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
    case STATE = 'state';
    case REQUISITION = 'requisition';
    case TEST = 'test';
    case AFREIGHTMENT = 'afreightment';
    case HUMANITARE = 'humanitare';

    public function label(): string
    {
        return match($this) {
            self::COMMERCIAL => 'Vol Commercial',
            self::STATE => 'Vol d\'État',
            self::REQUISITION => 'Vol de Réquisition',
            self::AFREIGHTMENT => 'Vol d\'Affrètement',
            self::TEST => 'Vol de Test',
            self::HUMANITARE => 'Vol Humanitaire',
        };
    }

    public function isCommercial(): bool
    {
        return $this === self::COMMERCIAL;
    }

    public function isNonCommercial(): bool
    {
        return !$this->isCommercial();
    }

    /**
     * Get all commercial cases
     */
    public static function commercial(): array
    {
        return [self::COMMERCIAL];
    }

    /**
     * Get all non-commercial cases
     */
    public static function nonCommercial(): array
    {
        return array_filter(
            self::cases(),
            fn($case) => $case->isNonCommercial()
        );
    }

    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn($case) => [$case->value => $case->label()])
            ->toArray();
    }
}