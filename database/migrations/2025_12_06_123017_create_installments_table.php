<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('installments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();

        $table->integer('installment_number');
        $table->date('due_date');
        $table->decimal('amount_due', 18, 2);
        $table->decimal('amount_paid', 18, 2)->default(0);
        $table->timestamp('paid_at')->nullable();
        $table->enum('status', ['unpaid', 'paid', 'overdue'])->default('unpaid');
        $table->decimal('penalty_fee', 18, 2)->default(0);

        $table->timestamps();
        $table->softDeletes();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('installments');
    }
};
