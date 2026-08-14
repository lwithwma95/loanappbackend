<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank', function (Blueprint $table) {
            $table->string('city', 100)->nullable()->after('address');
            $table->string('state', 100)->nullable()->after('city');
            $table->string('micr', 15)->nullable()->after('state');
            $table->string('contact', 30)->nullable()->after('micr');
            $table->boolean('imps')->nullable()->after('contact');
            $table->boolean('rtgs')->nullable()->after('imps');
            $table->boolean('neft')->nullable()->after('rtgs');
            $table->boolean('upi')->nullable()->after('neft');
            $table->string('source', 20)->nullable()->after('upi'); // 'manual' | 'razorpay'
        });
    }

    public function down(): void
    {
        Schema::table('bank', function (Blueprint $table) {
            $table->dropColumn(['city', 'state', 'micr', 'contact', 'imps', 'rtgs', 'neft', 'upi', 'source']);
        });
    }
};
