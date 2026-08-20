<?php

// | KB @CerberRus00 - Nexus Invest Team
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('subject', 512);
            $table->string('chain_id', 32)->nullable();
            $table->string('status', 32);
            $table->string('verdict', 32)->nullable();
            $table->unsignedTinyInteger('risk_score')->default(0);
            $table->string('locale', 8)->default('en');
            $table->string('provider_request_id')->nullable();
            $table->json('flags')->nullable();
            $table->json('raw_response')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['type', 'verdict']);
            $table->index('subject');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checks');
    }
};
