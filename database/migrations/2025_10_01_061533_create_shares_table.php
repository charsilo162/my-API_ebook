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
        Schema::create('shares', function (Blueprint $table) {
            $table->id();

            // Link to the User who performed the share
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            
            // Platform where the sharing occurred (e.g., 'Facebook', 'Email')
            $table->string('platform');

            // Polymorphic Columns:
            // This creates 'shareable_id' (unsignedBigInteger) and 'shareable_type' (string)
            $table->morphs('shareable'); 

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shares');
    }
};