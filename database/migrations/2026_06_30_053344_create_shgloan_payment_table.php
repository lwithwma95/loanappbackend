<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shgloan_payment', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loan_id');
            $table->decimal('payment_amount', 10, 2);
            $table->date('date_payment');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->string('transaction_id', 100)->nullable();
            $table->enum('mode_of_payment', ['cash', 'bank', 'online', 'cheque', 'upi'])->default('cash');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('loan_id')->references('id')->on('shgloan')->cascadeOnDelete();
            $table->foreign('updated_by')->references('id')->on('block_cadres')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shgloan_payment');
    }
};
