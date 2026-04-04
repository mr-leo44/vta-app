<?php

namespace Database\Seeders;

use App\Enums\Permission as EnumsPermission;
use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (EnumsPermission::cases() as $perm) {
            Permission::firstOrCreate([
                'name' => $perm->value,
                'guard_name' => 'web'
            ]);
        }

        // define roles  for Admin and attach permissions
        $admin = Role::firstOrCreate([
            'name' => UserRole::ADMIN->value,
            'guard_name' => 'web'
        ]);
        $admin->syncPermissions(EnumsPermission::all());

        // define roles  for Manager and attach permissions
        $manager = Role::firstOrCreate([
            'name' => UserRole::MANAGER->value,
            'guard_name' => 'web'
        ]);
        $manager->syncPermissions(EnumsPermission::forManager());

        // define roles  for Permanent Agents and attach permissions
        $permanent_agent = Role::firstOrCreate([
            'name' => UserRole::PERMANENT->value,
            'guard_name' => 'web'
        ]);
        $permanent_agent->syncPermissions(EnumsPermission::forPermanent());

        // define roles  for Agent and attach permissions
        $agent = Role::firstOrCreate([
            'name' => UserRole::AGENT->value,
            'guard_name' => 'web'
        ]);
        $agent->syncPermissions(EnumsPermission::forAgent());

        $this->command->info('✅ Permissions et rôles synchronisés.');
        $this->command->table(
            ['Rôle', 'Nb permissions'],
            [
                [UserRole::ADMIN->label(),   count(EnumsPermission::all())],
                [UserRole::MANAGER->label(), count(EnumsPermission::forManager())],
                [UserRole::PERMANENT->label(),   count(EnumsPermission::forPermanent())],
                [UserRole::AGENT->label(),   count(EnumsPermission::forAgent())],
            ]
        );
    }
}
