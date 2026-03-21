<?php

namespace App\Enums;

enum UserFunction: string
{
    // ── CS (Chef de Service) ──────────────────────────────────────────────
    case CS         = 'CS';
    case CS_AI      = 'CS ai';

    // ── CB-IDEF ───────────────────────────────────────────────────────────
    case CB_IDEF    = 'CB-IDEF';
    case CB_IDEF_AI = 'CB-IDEF ai';

    // ── CB Trafic ─────────────────────────────────────────────────────────
    case CB_TRAFIC     = 'CB Trafic';
    case CB_TRAFIC_AI  = 'CB-Trafic ai';

    // ── CB Pax Bus ────────────────────────────────────────────────────────
    case CB_PAXBUS     = 'CB PAXBUS';
    case CB_PAXBUS_AI  = 'CB-PAXBUS ai';

    // ── CQ / CQA (Québec) ─────────────────────────────────────────────────
    case CQ  = 'CQ';
    case CQA = 'CQA';

    // ── VTA ───────────────────────────────────────────────────────────────
    case VTA        = 'VTA';
    case VTA_TRAFIC = 'VTA-Trafic';
    case VTA_IDEF   = 'VTA-IDEF';
    case VTA_PAXBUS = 'VTA-PAXBUS';

    // ── VTA Superviseur et encodeur ───────────────────────────────────────
    case VTA_SUP      = 'VTA-Sup';
    case VTA_ENCODEUR = 'VTA-Encodeur';

    // ─────────────────────────────────────────────────────────────────────
    // Singleton : une seule personne active par fonction à la fois
    // ─────────────────────────────────────────────────────────────────────

    public function isSingleton(): bool
    {
        return match ($this) {
            self::CS,
            self::CS_AI,
            self::CB_TRAFIC,
            self::CB_TRAFIC_AI,
            self::CB_IDEF,
            self::CB_IDEF_AI,
            self::CB_PAXBUS,
            self::CB_PAXBUS_AI  => true,
            default    => false,
        };
    }

    // ─────────────────────────────────────────────────────────────────────
    // Mapping fonction → rôle Spatie
    // ─────────────────────────────────────────────────────────────────────

    public function role(): UserRole
    {
        return match ($this) {
            self::VTA_ENCODEUR,
            self::VTA_SUP                    => UserRole::ADMIN,

            self::CS,
            self::CS_AI,
            self::CB_IDEF,
            self::CB_IDEF_AI,
            self::CB_TRAFIC,
            self::CB_TRAFIC_AI,
            self::CB_PAXBUS,
            self::CB_PAXBUS_AI,
            self::CQ,                        // ← Québec → Manager
            self::CQA                        => UserRole::MANAGER, // ← Québec Alpha → Manager

            self::VTA,
            self::VTA_TRAFIC,
            self::VTA_IDEF,
            self::VTA_PAXBUS                 => UserRole::AGENT,
        };
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    public function label(): string
    {
        return match ($this) {
            self::CQ  => 'Québec',
            self::CQA => 'Québec Alpha',
            default   => $this->value,
        };
    }

    /** Retourne toutes les fonctions mappées sur un rôle donné. */
    public static function forRole(UserRole $role): array
    {
        return array_filter(
            self::cases(),
            fn (self $fn) => $fn->role() === $role
        );
    }

    /** Retourne les labels pour les selects du front. */
    public static function options(): array
    {
        return array_map(
            fn (self $fn) => ['value' => $fn->value, 'label' => $fn->label()],
            self::cases()
        );
    }
}
