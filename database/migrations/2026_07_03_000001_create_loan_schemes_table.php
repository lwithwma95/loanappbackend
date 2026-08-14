<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_schemes', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name', 200);
            $table->string('scheme_name', 200);
            $table->timestamps();

            $table->index('bank_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_schemes');
    }
};
