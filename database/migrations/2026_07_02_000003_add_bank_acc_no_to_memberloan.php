<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberloan', function (Blueprint $table) {
            $table->string('bank_acc_no', 30)->nullable()->after('ifsc');
        });
    }

    public function down(): void
    {
        Schema::table('memberloan', function (Blueprint $table) {
            $table->dropColumn('bank_acc_no');
        });
    }
};
