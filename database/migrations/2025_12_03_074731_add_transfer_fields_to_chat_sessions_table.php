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
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('transferred_to')->nullable();
            $table->unsignedBigInteger('transferred_by')->nullable();
            $table->timestamp('transferred_at')->nullable();
            $table->json('admin_participants')->nullable();
            
            $table->foreign('transferred_to')->references('id')->on('users')->onDelete('set null');
            $table->foreign('transferred_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            //
        });
    }
};
