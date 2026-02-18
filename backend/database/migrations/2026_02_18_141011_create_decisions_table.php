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
        Schema::create('decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('category', ['financial', 'health', 'work', 'social', 'mindset', 'other']);
            $table->json('context_json');
            $table->json('ai_response_json')->nullable();
            $table->text('final_choice')->nullable();
            $table->text('outcome_notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'category']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('decisions');
    }
};
