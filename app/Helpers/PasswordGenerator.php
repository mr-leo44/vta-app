<?php

namespace App\Helpers;

use Illuminate\Support\Str;

/**
 * PasswordGenerator — génère des passwords sûrs et aléatoires.
 */
class PasswordGenerator
{
    /**
     * Génère un password de 16 caractères minimum.
     * Comprend uppercase, lowercase, numbers, et symbols.
     */
    public static function generate(int $length = 16): string
    {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $symbols = '!@#$%^&*()_+-=[]{}|;:,.<>?';

        $allChars = $uppercase . $lowercase . $numbers . $symbols;

        $password = '';
        
        // S'assurer au moins 1 caractère de chaque type
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $symbols[random_int(0, strlen($symbols) - 1)];

        // Remplir le reste aléatoirement
        for ($i = 4; $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }

        // Mélanger pour éviter un pattern
        return str_shuffle($password);
    }
}
