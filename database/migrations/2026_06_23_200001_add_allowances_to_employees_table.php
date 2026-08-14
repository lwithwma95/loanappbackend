<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('mobile_allowance', 12, 2)->default(1500)->after('salary');
            $table->decimal('professional_dev', 12, 2)->default(2500)->after('mobile_allowance');
        });
    }
    public function down(): void {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['mobile_allowance', 'professional_dev']);
        });
    }
};
