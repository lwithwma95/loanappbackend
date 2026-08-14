<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank', function (Blueprint $table) {
            // Allow the same IFSC to be recorded for multiple districts.
            // Uniqueness is not enforced at the DB level; pullIfsc deduplicates in-app.
            if (collect(\DB::select("SHOW INDEX FROM bank WHERE Key_name = 'bank_ifsc_branch_unique'"))->isNotEmpty()) {
                $table->dropUnique('bank_ifsc_branch_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bank', function (Blueprint $table) {
            $table->unique(['ifsc', 'branch_name'], 'bank_ifsc_branch_unique');
        });
    }
};
