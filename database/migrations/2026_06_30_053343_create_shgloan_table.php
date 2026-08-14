<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shgloan', function (Blueprint $table) {
            $table->id();
            $table->string('shg_code', 100)->index(); // no FK — shg may not exist in `shg` table
            $table->integer('district_id')->nullable();
            $table->integer('block_id')->nullable();
            $table->string('loan_id', 50)->unique();
            $table->decimal('loan_amount', 12, 2);
            $table->decimal('total_due', 12, 2)->default(0);
            $table->decimal('already_paid', 12, 2)->default(0);
            $table->date('loan_sanction_date')->nullable();
            $table->date('payment_date')->nullable();
            $table->decimal('interest_rate', 5, 2)->default(0);
            $table->integer('tenure_months')->nullable();
            $table->decimal('emi_amount', 10, 2)->nullable();
            $table->string('bank_acc_no', 30)->nullable();
            $table->string('ifsc', 11)->nullable();
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->string('bank_sakhi', 150)->nullable(); // SHG member liaising with the bank
            $table->unsignedBigInteger('monitoring_cadre')->nullable();
            $table->enum('status', ['pending', 'active', 'closed', 'npa'])->default('pending');
            $table->string('bank_status', 20)->nullable(); // bank-reported status for comparison
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('district_id')->references('district_id')->on('district')->nullOnDelete();
            $table->foreign('block_id')->references('block_id')->on('block')->nullOnDelete();
            $table->foreign('bank_id')->references('id')->on('bank')->nullOnDelete();
            $table->foreign('monitoring_cadre')->references('id')->on('block_cadres')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shgloan');
    }
};
