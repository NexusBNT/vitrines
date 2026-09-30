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
        Schema::table('media', function (Blueprint $table) {
            $table->string('source', 16)->default('upload')->after('site_id');
            $table->string('ai_slot', 32)->nullable()->after('source');
            $table->text('ai_prompt')->nullable()->after('ai_slot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn(['source', 'ai_slot', 'ai_prompt']);
        });
    }
};
