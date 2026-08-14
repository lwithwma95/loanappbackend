<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('shg', 'shg_status')) {
            Schema::table('shg', function (Blueprint $table) {
                $table->tinyInteger('shg_status')->default(1)->after('shg_name');
            });
        }
    }

    public function down(): void
    {
        Schema::table('shg', function (Blueprint $table) {
            $table->dropColumn('shg_status');
        });
    }
};
