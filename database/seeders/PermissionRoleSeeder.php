<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class PermissionRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // define some example permissions
        $permissions = [
            'view reports',
            'export reports',
            'manage users',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // define roles and attach permissions
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin-> givePermissionTo(Permission::all());

        $manager = Role::firstOrCreate(['name' => 'manager']);
        $manager->givePermissionTo(['view reports', 'export reports']);

        $user = Role::firstOrCreate(['name' => 'user']);

        // Optionally assign admin role to the first user
        $first = User::find(1);
        if ($first) {
            $first->assignRole('admin');
        }
    }
}
