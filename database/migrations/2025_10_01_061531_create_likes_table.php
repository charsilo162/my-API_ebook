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
        Schema::create('likes', function (Blueprint $table) {
            $table->id();

            // Link to the User who performed the like
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->morphs('likeable'); 
           $table->enum('type', ['up', 'down']); 

            // Ensures a user can only vote (up or down) on a specific item once
            $table->unique(['user_id', 'likeable_id', 'likeable_type']); 

            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('likes');
    }
};