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
    Schema::create('orders', function (Blueprint $table) {
        $table->id();
        $table->string('order_number', 30)->unique();

        $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
        $table->foreignId('car_id')->constrained('cars')->cascadeOnDelete();

        $table->date('date');
        $table->enum('payment_type', ['cash', 'credit']);
        $table->decimal('total_price', 18, 2);

        // Kredit
        $table->foreignId('credit_package_id')->nullable()->constrained('credit_packages')->nullOnDelete();
        $table->decimal('down_payment', 18, 2)->default(0);
        $table->decimal('interest_amount', 18, 2)->default(0);
        $table->decimal('monthly_installment', 18, 2)->default(0);

        $table->enum('status', ['pending', 'approved', 'rejected', 'completed'])->default('pending');
        $table->text('notes')->nullable();

        $table->timestamps();
        $table->softDeletes(); // Penting banget buat Order
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
