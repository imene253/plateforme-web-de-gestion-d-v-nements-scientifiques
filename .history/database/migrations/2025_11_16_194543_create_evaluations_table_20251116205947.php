<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('submissions')->onDelete('cascade');
            $table->foreignId('evaluator_id')->constrained('users')->onDelete('cascade');
            $table->integer('relevance_score')->nullable(); // درجة الملاءمة (1-10)
            $table->integer('scientific_quality_score')->nullable(); // الجودة العلمية (1-10)
            $table->integer('originality_score')->nullable(); 
            $table->text('comments')->nullable();
            $table->enum('recommendation', ['accept', 'reject', 'revision'])->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamps();
            
           
            $table->unique(['submission_id', 'evaluator_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('evaluations');
    }
};