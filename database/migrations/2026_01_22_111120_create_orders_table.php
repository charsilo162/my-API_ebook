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
            $table->foreignId('user_id')->constrained();
            $table->decimal('total_amount', 15, 2);
            $table->string('reference')->nullable();
        // Status: pending, processing, shipped, delivered, completed
             $table->string('status')->default('pending');
            $table->string('payment_status')->default('pending'); // pending, paid, failed
            $table->string('order_type')->default('instant'); // shipping or instant download
            $table->timestamps();
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
