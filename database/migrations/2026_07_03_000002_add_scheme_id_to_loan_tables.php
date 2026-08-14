<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberloan', function (Blueprint $table) {
            $table->foreignId('scheme_id')->nullable()->constrained('loan_schemes')->nullOnDelete()->after('bank_id');
        });

        Schema::table('shgloan', function (Blueprint $table) {
            $table->foreignId('scheme_id')->nullable()->constrained('loan_schemes')->nullOnDelete()->after('bank_id');
        });
    }

    public function down(): void
    {
        Schema::table('memberloan', function (Blueprint $table) {
            $table->dropForeign(['scheme_id']);
            $table->dropColumn('scheme_id');
        });

        Schema::table('shgloan', function (Blueprint $table) {
            $table->dropForeign(['scheme_id']);
            $table->dropColumn('scheme_id');
        });
    }
};
