<?php

namespace Database\Seeders;

use App\Models\Admin;
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
        $this->call(RolesAndPermissionsSeeder::class);

        $admin = Admin::query()->firstOrCreate(
            ['email' => config('business.seed_admin.email')],
            ['name' => 'Super Admin', 'password' => config('business.seed_admin.password')],
        );
        $admin->assignRole('admin');

        User::factory()->create([
            'name' => 'Test Member',
            'email' => 'test@example.com',
        ])->assignRole('member');
    }
}
