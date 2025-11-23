<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run()
    {
        // Use firstOrCreate to avoid duplicates
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@scientificevents.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('admin123456'),
                'phone' => '+213 555 000 000',
                'institution' => 'Platform Administration',
                'country' => 'Algeria',
                'is_active' => true, // ← Add this line!
                'email_verified_at' => now(),
            ]
        );

        // Assign super admin role
        if (!$superAdmin->hasRole('super_admin')) {
            $superAdmin->assignRole('super_admin');
        }

        $this->command->info('Super Admin created successfully!');
        $this->command->info('Email: admin@scientificevents.com');
        $this->command->info('Password: admin123456');
        $this->command->info('Active Status: ' . ($superAdmin->is_active ? 'Active' : 'Inactive'));
    }
}