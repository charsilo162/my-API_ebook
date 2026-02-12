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
     Schema::create('book_variants', function (Blueprint $table) 
     {
            $table->id();
            $table->foreignId('book_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['digital', 'physical']); 
            $table->decimal('price', 10, 2);
            $table->decimal('discount_price', 10, 2)->nullable(); // Sale price
            $table->integer('stock_quantity')->default(0); // Use -1 or high number for digital
            $table->string('file_path')->nullable(); // For PDF/Epub download
            $table->foreignId('bookshop_id')->nullable()->constrained(); // Link to a specific shop if physical
            $table->unique(['book_id', 'type']);
            $table->timestamps();
        }
        
        );
    
        }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_variants');
    }
};
