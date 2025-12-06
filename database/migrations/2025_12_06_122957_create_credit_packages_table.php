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
    Schema::create('credit_packages', function (Blueprint $table) {
        $table->id();
        $table->string('code', 20);
        $table->integer('tenor');
        $table->decimal('interest_rate', 5, 2);
        $table->decimal('down_payment_rule', 18, 2);

        $table->timestamps();
        $table->softDeletes();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_packages');
    }
};
