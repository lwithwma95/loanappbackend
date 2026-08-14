<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank', function (Blueprint $table) {
            // Remove district-scoped uniqueness — same IFSC is allowed across districts
            $table->dropUnique('bank_ifsc_district_unique');

            // Uniqueness is now: same IFSC + same branch_name = duplicate
            // Same IFSC with a different branch_name is allowed (e.g. SBI Main vs SBI City)
            $table->unique(['ifsc', 'branch_name'], 'bank_ifsc_branch_unique');
        });
    }

    public function down(): void
    {
        Schema::table('bank', function (Blueprint $table) {
            $table->dropUnique('bank_ifsc_branch_unique');
            $table->unique(['ifsc', 'district_id'], 'bank_ifsc_district_unique');
        });
    }
};
