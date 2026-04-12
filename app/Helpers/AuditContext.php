<?php

namespace App\Helpers;

/**
 * AuditContext — tracker l'utilisateur courant (Auth ou Job)
 * 
 * Utilisé par:
 * - AuditObserver: pour remplir created_by/updated_by
 * - Jobs: pour simuler un user context sans Auth
 */
class AuditContext
{
    private static ?int $userId = null;

    /**
     * Définir l'utilisateur pour la durée du contexte.
     */
    public static function setUserId(?int $userId): void
    {
        self::$userId = $userId;
    }

    /**
     * Récupérer l'utilisateur courant.
     */
    public static function getUserId(): ?int
    {
        return self::$userId;
    }

    /**
     * Réinitialiser le contexte.
     */
    public static function reset(): void
    {
        self::$userId = null;
    }

    /**
     * Exécuter un callback avec un user_id défini.
     */
    public static function withUserId(?int $userId, callable $callback)
    {
        self::setUserId($userId);
        try {
            return $callback();
        } finally {
            self::reset();
        }
    }
}
