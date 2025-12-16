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
        Schema::create('workshop_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->onDelete('cascade');
            
            $table->string('title');
            $table->text('description')->nullable();
            
            $table->string('type'); // Resource Type ('pdf', 'link', 'video')
            
            // Storage/URL
            $table->string('file_path')->nullable(); // Used for PDFs/uploaded files
            $table->string('external_url')->nullable(); // Used for links/videos
            
            $table->boolean('is_public')->default(false); // If true, non-participants can see it
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workshop_materials');
    }
};
