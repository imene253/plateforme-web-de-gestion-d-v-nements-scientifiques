<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable();
            $table->string('institution')->nullable();
            $table->string('research_field')->nullable();
            $table->text('biography')->nullable();
            $table->string('profile_photo')->nullable();
            $table->string('country')->nullable();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'institution',
                'research_field',
                'biography',
                'profile_photo',
                'country'
            ]);
        });
    }
};