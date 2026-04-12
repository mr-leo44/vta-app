<?php

namespace App\Enums;

enum Permission: string
{
    // ── Vols ──────────────────────────────────────────────────────────────
    case FLIGHT_VIEW           = 'flight.view';
    case FLIGHT_CREATE         = 'flight.create';
    case FLIGHT_UPDATE_OWN     = 'flight.updateOwn';      // agent: ses vols uniquement
    case FLIGHT_UPDATE_ANY     = 'flight.updateAny';      // admin/manager
    case FLIGHT_DELETE_OWN     = 'flight.deleteOwn';
    case FLIGHT_DELETE_ANY     = 'flight.deleteAny';
    case FLIGHT_VALIDATE       = 'flight.validate';       // admin: valide les vols agents
    case FLIGHT_EXPORT         = 'flight.export';

        // ── Avions ────────────────────────────────────────────────────────────
    case AIRCRAFT_VIEW         = 'aircraft.view';
    case AIRCRAFT_CREATE       = 'aircraft.create';
    case AIRCRAFT_UPDATE       = 'aircraft.update';
    case AIRCRAFT_DELETE       = 'aircraft.delete';

        // ── Types d'avion ─────────────────────────────────────────────────────
    case AIRCRAFT_TYPE_VIEW     = 'aircraftType.view';
    case AIRCRAFT_TYPE_CREATE   = 'aircraftType.create';
    case AIRCRAFT_TYPE_UPDATE   = 'aircraftType.update';
    case AIRCRAFT_TYPE_DELETE   = 'aircraftType.delete';

        // ── Opérateurs ────────────────────────────────────────────────────────
    case OPERATOR_VIEW         = 'operator.view';
    case OPERATOR_CREATE       = 'operator.create';
    case OPERATOR_UPDATE       = 'operator.update';
    case OPERATOR_DELETE       = 'operator.delete';

        // ── Rapports ──────────────────────────────────────────────────────────
    case REPORT_VIEW           = 'report.view';
    case REPORT_EXPORT         = 'report.export';

        // ── Utilisateurs ──────────────────────────────────────────────────────
    case USER_VIEW                  = 'user.view';
    case USER_CREATE                = 'user.create';
    case USER_UPDATE                = 'user.update';
    case USER_DELETE                = 'user.delete';
    case USER_ASSIGN_FUNCTION       = 'user.assignFunction';
    case USER_RESET_PASSWORD        = 'user.resetPassword';
    case USER_RESET_PAWWORD_REQUEST = 'user.resetPasswordRequest';
    case USER_UPDATE_PROFILE        = 'user.updateProfile';
    case USER_CHANGE_PASSWORD       = 'user.changePassword';

        // ── Permissions ────────────────────────────────────────────
    case PERMISSION_VIEW           = 'permission.view';
    case PERMISSION_VIEW_OWN       = 'permission.viewOwn';
    case PERMISSION_REQUEST_CREATE = 'permissionRequest.create';
    case PERMISSION_REQUEST_MANAGE = 'permissionRequest.manage'; // admin uniquement

        // ── Imports & Export ───────────────────────────────────────────
    case FILES_IMPORT = 'files.import';
    case FILES_EXPORT = 'files.export';

        // ── Trafic ────────────────────────────────────────────
    case PAX_UPDATE         = 'pax.update';
    case FREIGHT_UPDATE     = 'freight.update';
    case EXCEDENT_UPDATE    = 'excedent.update';

        // ── IDEF ────────────────────────────────────────────
    case GOPASS_UPDATE = 'gopass.update';

        // ── PAX BUS ────────────────────────────────────────────
    case PAXBUS_UPDATE = 'paxbus.update';

    // ───────────────────────────────────────────────────────────────────
    // Sets par rôle
    // ───────────────────────────────────────────────────────────────────

    /** Toutes les permissions → Admin. */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Permissions Manager : lecture + rapports, pas de validation ni gestion users. */
    public static function forManager(): array
    {
        return [
            self::FLIGHT_VIEW->value,
            self::FLIGHT_CREATE->value,
            self::FLIGHT_UPDATE_ANY->value,
            self::FLIGHT_VIEW->value,
            self::FLIGHT_DELETE_ANY->value,
            self::FLIGHT_EXPORT->value,

            self::AIRCRAFT_VIEW->value,
            self::AIRCRAFT_CREATE->value,
            self::AIRCRAFT_UPDATE->value,
            self::AIRCRAFT_DELETE->value,

            self::AIRCRAFT_TYPE_VIEW->value,
            self::AIRCRAFT_TYPE_CREATE->value,
            self::AIRCRAFT_TYPE_UPDATE->value,
            self::AIRCRAFT_TYPE_DELETE->value,

            self::OPERATOR_VIEW->value,
            self::OPERATOR_CREATE->value,
            self::OPERATOR_UPDATE->value,
            self::OPERATOR_DELETE->value,

            self::REPORT_VIEW->value,
            self::REPORT_EXPORT->value,

            self::USER_RESET_PAWWORD_REQUEST->value,
            self::USER_UPDATE_PROFILE->value,
            self::USER_CHANGE_PASSWORD->value,

            self::PERMISSION_VIEW_OWN->value,
            self::PERMISSION_REQUEST_CREATE->value,
        ];
    }

    /** Permissions Agent : encodage vols (ses propres uniquement). */
    public static function forPermanent(): array
    {
        return [
            self::FLIGHT_VIEW->value,

            self::USER_RESET_PAWWORD_REQUEST->value,

            self::PERMISSION_VIEW_OWN->value,
            self::PERMISSION_REQUEST_CREATE->value,

            self::PAX_UPDATE->value,
            self::FREIGHT_UPDATE->value,
            self::PAXBUS_UPDATE->value,
            self::GOPASS_UPDATE->value,
        ];
    }

    public static function forAgent(): array
    {
        return [
            self::FLIGHT_VIEW->value,
            self::FLIGHT_CREATE->value,
            self::FLIGHT_UPDATE_OWN->value,
            self::FLIGHT_DELETE_OWN->value,

            self::AIRCRAFT_CREATE->value,
            self::AIRCRAFT_UPDATE->value,

            self::AIRCRAFT_TYPE_CREATE->value,
            self::AIRCRAFT_TYPE_UPDATE->value,

            self::OPERATOR_CREATE->value,
            self::OPERATOR_UPDATE->value,

            self::USER_RESET_PAWWORD_REQUEST->value,
            self::USER_UPDATE_PROFILE->value,
            self::USER_CHANGE_PASSWORD->value,

            self::PERMISSION_VIEW_OWN->value,
            self::PERMISSION_REQUEST_CREATE->value,
        ];
    }
}
