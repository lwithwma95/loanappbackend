<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberloan_payment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('memberloan')->cascadeOnDelete();
            $table->decimal('payment_amount', 10, 2);
            $table->date('date_payment');
            $table->unsignedBigInteger('updated_by')->nullable();   // FK to block_cadres.id
            $table->string('transaction_id', 100)->nullable();
            $table->enum('mode_of_payment', ['cash', 'bank', 'online', 'cheque', 'upi'])->default('cash');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('updated_by')->references('id')->on('block_cadres')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberloan_payment');
    }
};
