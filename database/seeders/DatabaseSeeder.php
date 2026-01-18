<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([PermissionRoleSeeder::class]);
        $admin = User::factory()->create([
            'name' => 'Administrateur',
            'username' => 'admin',
            'password' => 'Admin@123!',
        ]);
        
        $admin->assignRole('admin');
    }
}