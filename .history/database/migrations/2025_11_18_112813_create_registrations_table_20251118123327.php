<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('registration_type', ['participant', 'author', 'guest_speaker', 'workshop_facilitator']);
            $table->enum('payment_status', ['unpaid', 'paid', 'pending', 'refunded'])->default('unpaid');
            $table->decimal('amount', 10, 2)->nullable();
            $table->json('additional_info')->nullable();
            $table->timestamp('registered_at');
            $table->timestamp('payment_date')->nullable();
            $table->string('payment_method')->nullable(); // cash, bank_transfer, online
            $table->text('notes')->nullable();
            $table->boolean('is_confirmed')->default(false);
            $table->string('confirmation_code')->unique()->nullable();
            $table->timestamps();
            
            
            $table->unique(['event_id', 'user_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('registrations');
    }
};