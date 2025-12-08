<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([PermissionRoleSeeder::class]);
        if (User::count() === 0) {
            User::factory()->create([
                'name' => 'Administrateur',
                'username' => 'admin',
                'password' => Hash::make('admin@1234!'),
            ])->assignRole('Admin');
        }
    }
}