<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberloan', function (Blueprint $table) {
            $table->timestamp('bank_reconcile_date')->nullable()->after('bank_os_balance');
        });
    }

    public function down(): void
    {
        Schema::table('memberloan', function (Blueprint $table) {
            $table->dropColumn('bank_reconcile_date');
        });
    }
};
