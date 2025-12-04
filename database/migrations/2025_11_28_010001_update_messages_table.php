<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations to add chat_session_id to messages
     */
    public function up(): void
    {
        if (Schema::hasTable('messages')) {
            // Add chat_session_id if it doesn't exist
            if (!Schema::hasColumn('messages', 'chat_session_id')) {
                Schema::table('messages', function (Blueprint $table) {
                    $table->foreignId('chat_session_id')
                        ->nullable()
                        ->constrained('chat_sessions')
                        ->onDelete('cascade')
                        ->after('id');
                });
            }

            // Make ticket_id nullable
            if (Schema::hasColumn('messages', 'ticket_id')) {
                try {
                    DB::statement('ALTER TABLE messages MODIFY ticket_id BIGINT UNSIGNED NULL');
                } catch (\Exception $e) {
                    // Column might already be nullable
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('messages')) {
            // Try to drop the foreign key first
            try {
                // Get the foreign key name
                $foreignKeys = DB::select(
                    "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'messages' AND COLUMN_NAME = 'chat_session_id'"
                );
                
                if (!empty($foreignKeys)) {
                    $fkName = $foreignKeys[0]->CONSTRAINT_NAME;
                    DB::statement("ALTER TABLE messages DROP FOREIGN KEY {$fkName}");
                }
            } catch (\Exception $e) {
                // Foreign key doesn't exist, continue
            }

            // Drop column if it exists
            if (Schema::hasColumn('messages', 'chat_session_id')) {
                Schema::table('messages', function (Blueprint $table) {
                    $table->dropColumn('chat_session_id');
                });
            }
        }
    }
};