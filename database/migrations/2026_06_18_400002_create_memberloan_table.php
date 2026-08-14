<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberloan', function (Blueprint $table) {
            $table->id();
            $table->string('mem_code', 50)->index();            // FK to member.mem_code
            $table->string('shg_code', 50)->nullable();         // denormalized
            $table->string('loan_id', 50)->unique();            // auto-generated: ML/YYYY/NNNN
            $table->decimal('loan_amount', 12, 2);
            $table->decimal('total_due', 12, 2);                // loan_amount + interest - already_paid
            $table->decimal('already_paid', 12, 2)->default(0);
            $table->date('loan_sanction_date');
            $table->date('payment_date')->nullable();           // next EMI due date
            $table->decimal('interest_rate', 5, 2)->default(0);
            $table->integer('tenure_months')->nullable();
            $table->decimal('emi_amount', 10, 2)->nullable();
            $table->string('ifsc', 11)->nullable();
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->unsignedBigInteger('monitoring_cadre')->nullable();  // FK to block_cadres.id
            $table->enum('status', ['pending', 'active', 'closed', 'npa'])->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('bank_id')->references('id')->on('bank')->nullOnDelete();
            $table->foreign('monitoring_cadre')->references('id')->on('block_cadres')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberloan');
    }
};
