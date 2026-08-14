<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberloan', function (Blueprint $table) {
            // Bank-reported values stored separately to enable discrepancy comparison.
            // Our side:  loan_amount / already_paid / total_due (OS balance)
            // Bank side: bank_loan_amount / bank_already_paid / bank_os_balance
            $table->decimal('bank_loan_amount',  12, 2)->nullable()->after('loan_amount');
            $table->decimal('bank_already_paid', 12, 2)->nullable()->after('already_paid');
            $table->decimal('bank_os_balance',   12, 2)->nullable()->after('total_due');
        });
    }

    public function down(): void
    {
        Schema::table('memberloan', function (Blueprint $table) {
            $table->dropColumn(['bank_loan_amount', 'bank_already_paid', 'bank_os_balance']);
        });
    }
};
