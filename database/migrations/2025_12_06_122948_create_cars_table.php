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
    Schema::create('cars', function (Blueprint $table) {
        $table->id();
        $table->string('chassis_number', 50)->unique();
        $table->string('engine_number', 50)->unique();
        $table->string('model', 100);
        $table->string('color', 50);
        $table->decimal('price', 18, 2);
        $table->enum('status', ['ready', 'booked', 'sold'])->default('ready');
        $table->string('image_path', 255)->nullable();

        $table->timestamps();
        $table->softDeletes();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cars');
    }
};
