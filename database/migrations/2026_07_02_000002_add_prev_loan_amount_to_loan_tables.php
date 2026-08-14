<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberloan', function (Blueprint $table) {
            $table->decimal('prev_loan_amount', 12, 2)->nullable()->after('loan_amount');
        });

        Schema::table('shgloan', function (Blueprint $table) {
            $table->decimal('prev_loan_amount', 12, 2)->nullable()->after('loan_amount');
        });
    }

    public function down(): void
    {
        Schema::table('memberloan', function (Blueprint $table) {
            $table->dropColumn('prev_loan_amount');
        });

        Schema::table('shgloan', function (Blueprint $table) {
            $table->dropColumn('prev_loan_amount');
        });
    }
};
