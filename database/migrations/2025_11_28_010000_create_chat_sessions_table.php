<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create chat_sessions table if it doesn't exist
        if (!Schema::hasTable('chat_sessions')) {
            Schema::create('chat_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_id')->constrained('tickets')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('ict_staff_id')->constrained('users')->onDelete('cascade');
                $table->enum('status', ['active', 'closed'])->default('active');
                $table->timestamp('started_at')->useCurrent();
                $table->timestamp('ended_at')->nullable();
                $table->timestamps();

                $table->index('ticket_id');
                $table->index('user_id');
                $table->index('ict_staff_id');
                $table->index(['status', 'ict_staff_id']);
            });
        } else {
            // If table exists, ensure it has all required columns
            if (!Schema::hasColumn('chat_sessions', 'ict_staff_id')) {
                Schema::table('chat_sessions', function (Blueprint $table) {
                    $table->foreignId('ict_staff_id')
                        ->after('user_id')
                        ->constrained('users')
                        ->onDelete('cascade');
                    $table->index('ict_staff_id');
                    $table->index(['status', 'ict_staff_id']);
                });
            }

            if (!Schema::hasColumn('chat_sessions', 'status')) {
                Schema::table('chat_sessions', function (Blueprint $table) {
                    $table->enum('status', ['active', 'closed'])->default('active')->after('ict_staff_id');
                });
            }

            if (!Schema::hasColumn('chat_sessions', 'started_at')) {
                Schema::table('chat_sessions', function (Blueprint $table) {
                    $table->timestamp('started_at')->useCurrent()->after('status');
                });
            }

            if (!Schema::hasColumn('chat_sessions', 'ended_at')) {
                Schema::table('chat_sessions', function (Blueprint $table) {
                    $table->timestamp('ended_at')->nullable()->after('started_at');
                });
            }
        }

        // Update messages table if needed
        if (Schema::hasTable('messages')) {
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
        // Drop messages.chat_session_id first (foreign key dependency)
        if (Schema::hasTable('messages')) {
            try {
                $foreignKeys = DB::select(
                    "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'messages' AND COLUMN_NAME = 'chat_session_id'"
                );
                
                if (!empty($foreignKeys)) {
                    $fkName = $foreignKeys[0]->CONSTRAINT_NAME;
                    DB::statement("ALTER TABLE messages DROP FOREIGN KEY {$fkName}");
                }
            } catch (\Exception $e) {
                // Continue
            }

            if (Schema::hasColumn('messages', 'chat_session_id')) {
                Schema::table('messages', function (Blueprint $table) {
                    $table->dropColumn('chat_session_id');
                });
            }
        }

        // Then drop chat_sessions table
        Schema::dropIfExists('chat_sessions');
    }
};