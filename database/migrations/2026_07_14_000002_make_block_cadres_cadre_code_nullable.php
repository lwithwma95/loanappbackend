<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('block_cadres', function (Blueprint $table) {
            $table->string('cadre_code', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('block_cadres', function (Blueprint $table) {
            $table->string('cadre_code', 50)->nullable(false)->change();
        });
    }
};
