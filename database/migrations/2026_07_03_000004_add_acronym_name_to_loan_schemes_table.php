<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_schemes', function (Blueprint $table) {
            $table->string('acronym_name', 50)->nullable()->after('bank_name');
            $table->index('acronym_name');
        });
    }

    public function down(): void
    {
        Schema::table('loan_schemes', function (Blueprint $table) {
            $table->dropIndex(['acronym_name']);
            $table->dropColumn('acronym_name');
        });
    }
};
