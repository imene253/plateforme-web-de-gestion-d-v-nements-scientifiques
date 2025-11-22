<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run()
    {
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@scientificevents.com',
            'password' => Hash::make('admin123456'),
            'phone' => '+213 555 000 000',
            'institution' => 'Platform Administration',
            'country' => 'Algeria',
        ]);

        $superAdmin->assignRole('super_admin');

      
    }
}