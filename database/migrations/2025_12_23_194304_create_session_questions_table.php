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
        Schema::create('session_questions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('session_id')->constrained('sesions')->onDelete('cascade');
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->text('content');
        $table->integer('upvotes_count')->default(0); 
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_questions');
    }
};
