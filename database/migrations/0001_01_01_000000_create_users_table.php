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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');           // ← Split name into first and last
            $table->string('last_name');            // ← Added last_name
            $table->string('department')->nullable();
            $table->string('designation')->nullable();
            $table->string('extension_number')->nullable();
            $table->string('email')->unique();
            $table->enum('role', ['admin', 'ict_staff', 'other_staff'])->default('other_staff');  // ← Added default
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};