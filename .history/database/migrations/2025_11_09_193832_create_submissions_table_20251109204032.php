<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->foreignId('author_id')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->text('authors'); // JSON array of authors
            $table->text('abstract');
            $table->text('keywords'); // JSON array
            $table->enum('type', ['oral', 'poster', 'affiche'])->default('oral');
            $table->string('pdf_file')->nullable();
            $table->enum('status', ['pending', 'accepted', 'rejected', 'revision'])->default('pending');
            $table->text('admin_notes')->nullable(); // ملاحظات من المنظم
            $table->date('submission_deadline')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('submissions');
    }
};