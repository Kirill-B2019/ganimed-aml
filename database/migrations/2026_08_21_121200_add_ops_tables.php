<?php

// | KB @CerberRus00 - Nexus Invest Team
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screening_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::table('checks', function (Blueprint $table) {
            $table->foreignId('previous_check_id')->nullable()->after('user_id')->constrained('checks')->nullOnDelete();
            $table->foreignId('case_id')->nullable()->after('previous_check_id')->constrained('screening_cases')->nullOnDelete();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('check_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('screening_cases')->nullOnDelete();
            $table->string('action', 64);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['check_id', 'created_at']);
        });

        Schema::create('watch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('screening_cases')->nullOnDelete();
            $table->foreignId('last_check_id')->nullable()->constrained('checks')->nullOnDelete();
            $table->string('type', 32);
            $table->string('subject', 512);
            $table->string('chain_id', 32)->nullable();
            $table->unsignedSmallInteger('interval_days')->default(7);
            $table->string('last_verdict', 32)->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'last_run_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('webhook_url', 512)->nullable()->after('is_admin');
            $table->string('webhook_secret', 128)->nullable()->after('webhook_url');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['webhook_url', 'webhook_secret']);
        });
        Schema::dropIfExists('watch_items');
        Schema::dropIfExists('activity_logs');
        Schema::table('checks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('case_id');
            $table->dropConstrainedForeignId('previous_check_id');
        });
        Schema::dropIfExists('screening_cases');
    }
};
