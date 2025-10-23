# VTA API

Petit service API pour gérer la production VTA, l'authentification par username/password (Sanctum), et la gestion des rôles/permissions via Spatie.

Ce README décrit :
- comment fonctionne l'auth (endpoints),
- comment Spatie est intégré,
- comment générer la documentation API avec Dedoc Scramble,
- spécifications d'export Excel (rapport mensuel/annuel) à implémenter.

## Endpoints d'authentification

- POST /api/login
  - body: { "username": "...", "password": "..." }
  - retourne : user (sans password) et token Sanctum
- POST /api/logout
  - protégé par `auth:sanctum` ; révoque le token courant
- GET /api/user
  - protégé par `auth:sanctum` ; retourne l'utilisateur authentifié

Les réponses sont formatées via le helper `App\Helpers\ApiResponse`.

## Architecture & Clean Code

- Repositories (ex: `App\Repositories\UserRepositoryInterface` + `EloquentUserRepository`)
- Services (ex: `App\Services\AuthServiceInterface` + `AuthService`)
- FormRequests (ex: `App\Http\Requests\LoginRequest`)
- Controllers (ex: `App\Http\Controllers\Api\AuthController`)

Ces couches respectent SOLID et facilitent les tests.

## Rôles & Permissions (Spatie)

Le projet utilise `spatie/laravel-permission` pour gérer les rôles et permissions.

- Le package est listé dans `composer.json`.
- Le modèle `App\Models\User` utilise déjà le trait `HasRoles`.

Installation (si pas déjà fait) :

```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

Exemples :

```php
$user->assignRole('admin');
$user->givePermissionTo('export reports');
```

Seule la role `admin` pourra réinitialiser les mots de passe (flux à implémenter côté admin).

## Documentation API — Dedoc Scramble

Ce projet utilise Dedoc Scramble (`dedoc/scramble`) pour générer la documentation à partir de fichiers Markdown et annotations dans le code.

Workflow recommandé :

1. Installer le package (si nécessaire) :

```bash
composer require --dev dedoc/scramble
```

2. Placer des fichiers de documentation Markdown dans `docs/` ou annoter les contrôleurs et FormRequests.

3. Commande pour générer la documentation statique :

```bash
php artisan scramble:render
# ou selon la configuration du package
```

4. Le rendu peut être exposé sous `public/docs` ou maintenu dans `docs/`.

Comment annoter un contrôleur / FormRequest :

- Dans un contrôleur, ajouter une courte description au-dessus de la méthode et un exemple de request/response en Markdown.
- Dans un `FormRequest`, ajouter les règles et décrire les champs.

Exemple rapide pour `/api/login` (contrôleur) :

```php
/**
 * Log in a user by username/password.
 *
 * Request example:
 * {
 *   "username": "jdoe",
 *   "password": "secret"
 * }
 *
 * Response 200:
 * {
 *   "success": true,
 *   "data": { "user": {...}, "token": "..." }
 * }
 */
public function login(LoginRequest $request) { ... }
```

Je peux enrichir automatiquement ces fichiers si tu veux (j'ai préparé des fichiers `docs/` initiaux pour auth, permissions et exports).

## Exports Excel (spécs)

L'application devra fournir des exports Excel/CSV pour les rapports de production. Voici la spécification proposée :

- Rapport mensuel : colonnes (date, shift, operator_id, production_count, defects_count, downtime_minutes, remarks)
- Rapport annuel : agrégation mensuelle, totaux, et éventuellement plusieurs feuilles dans le classeur (par ligne/atelier)

Recommandations d'implémentation :

- Utiliser `maatwebsite/excel` pour générer XLSX/CSV.
- Pour gros volumes, générer via queue (jobs) et stockage sur `storage/app/exports`.
- Endpoint pour lancer l'export (POST /api/exports/production) qui retourne 202 + job id, et endpoint pour récupérer le fichier fini.

## Tests

- Tests unitaires pour `AuthService` existent (happy path, bad credentials, logout). Exécuter via :

```bash
vendor/bin/pest
```

## To do / prochaines étapes

- Ajouter endpoints admin pour gestion des rôles et reset de mot de passe (admin only).
- Implémenter exports Excel avec `maatwebsite/excel` (classe d'export, job, endpoint).
- Générer documentation Scramble et la publier (public/docs).

Si tu veux, j'exécute maintenant :
- générer la doc Scramble et la placer dans `public/docs`,
- ou installer `maatwebsite/excel` et créer une première classe d'export minimal.
