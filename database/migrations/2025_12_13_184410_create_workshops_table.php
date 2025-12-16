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
        Schema::create('workshops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            
            $table->string('title');
            $table->text('description')->nullable();
            
            $table->foreignId('animator_id')->constrained('users')->onDelete('restrict'); 
            
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->string('room');

            $table->integer('max_participants'); 
            $table->integer('current_participants')->default(0); 
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workshops');
    }
};
