<?php

// app/Enums/HighlightCategory.php

namespace App\Enums;

enum HighlightCategoryEnum: string
{
    case NOUVELLE_ACQUISITION = 'nouvelle_acquisition';
    case NOUVELLE_COMPAGNIE = 'nouvelle_compagnie';
    case QRF = 'qrf';
    case AVION_PANNE = 'avion_panne';
    case AVION_REPARATION_EXTERNE = 'avion_reparation_externe';
    case RETOUR_AVION = 'retour_avion';
    case REPRISE_VOL = 'reprise_vol';
    case LONG_STATIONNEMENT = 'long_stationnement';
    case CESSATION_ACTIVITES = 'cessation_activites';
    case RAPATRIEMENT_AVION = 'rapatriement_avion';
    case VOL_ANNULE = 'vol_annule';
    case AUTRE = 'autre';

    public function label(): string
    {
        return match($this) {
            self::NOUVELLE_ACQUISITION => 'Nouvelle Acquisition',
            self::NOUVELLE_COMPAGNIE => 'Nouvelle Compagnie',
            self::QRF => 'QRF',
            self::AVION_PANNE => 'Avion en Panne',
            self::AVION_REPARATION_EXTERNE => 'Réparation Externe',
            self::RETOUR_AVION => 'Retour d\'Avion',
            self::REPRISE_VOL => 'Reprise des Vols',
            self::LONG_STATIONNEMENT => 'Long Stationnement',
            self::CESSATION_ACTIVITES => 'Cessation d\'Activités',
            self::RAPATRIEMENT_AVION => 'Rapatriement d\'Avion',
            self::VOL_ANNULE => 'Vol Annulé',
            self::AUTRE => 'Autre',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}