<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('block_cadres', function (Blueprint $table) {
            $table->timestamp('consent_given_at')->nullable()->after('date_of_birth');
        });
    }

    public function down(): void
    {
        Schema::table('block_cadres', function (Blueprint $table) {
            $table->dropColumn('consent_given_at');
        });
    }
};
