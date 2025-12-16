<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('workshop_registrations', function (Blueprint $table) {
            // These columns are needed for the registration form data
            $table->text('reason_for_interest')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workshop_registrations', function (Blueprint $table) {
            $table->dropColumn(['reason_for_interest']);
        });
    }
};
