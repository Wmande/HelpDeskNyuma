<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            
            // Foreign key reference to users
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // User details (copied from users table at time of ticket creation)
            $table->string('first_name', 255);
            $table->string('last_name', 255);
            $table->string('department', 255)->nullable();

            // Ticket details
            $table->string('phone_number', 20);
            $table->string('room_number', 50);
            $table->text('description');
            $table->enum('status', ['open', 'in_progress', 'completed', 'escalated', 'closed'])->default('open');

            // Foreign key reference for assigned ICT staff
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};