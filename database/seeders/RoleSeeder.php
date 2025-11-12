<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run()
    {
        //  الأدوار المطلوبة  
        $roles = [
            'super_admin',
            'event_organizer',
            'author',
            'scientific_committee',
            'participant',
            'guest_speaker',
            'workshop_facilitator',
        ];
        // إنشاء الأدوار في قاعدة البيانات
        foreach ($roles as $role) {
            Role::create(['name' => $role]);
        }

        $this->command->info('تم إنشاء الأدوار بنجاح!');
    }
}