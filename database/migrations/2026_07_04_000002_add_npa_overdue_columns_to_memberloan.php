<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberloan', function (Blueprint $table) {
            $table->decimal('overdue_amount', 12, 2)->nullable()->after('total_due');
            $table->date('npa_date')->nullable()->after('bank_status');
        });
    }

    public function down(): void
    {
        Schema::table('memberloan', function (Blueprint $table) {
            $table->dropColumn(['overdue_amount', 'npa_date']);
        });
    }
};
