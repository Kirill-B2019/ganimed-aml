<?php

// | KB @CerberRus00 - Nexus Invest Team
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screening_cases', function (Blueprint $table) {
            $table->string('slug', 40)->nullable()->after('user_id');
            $table->unique(['user_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('screening_cases', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'slug']);
            $table->dropColumn('slug');
        });
    }
};
