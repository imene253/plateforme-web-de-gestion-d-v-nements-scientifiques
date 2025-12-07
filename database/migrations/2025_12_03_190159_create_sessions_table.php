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
        Schema::create('sesions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
        $table->string('title');
        $table->string('room');
        $table->dateTime('start_time');
        $table->dateTime('end_time');
        $table->foreignId('session_chair_id')->nullable()->constrained('users')->onDelete('set null');
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sesions');
    }
};

