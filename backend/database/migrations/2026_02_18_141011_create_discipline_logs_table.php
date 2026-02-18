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
        Schema::create('discipline_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('decision_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('log_type', ['complied', 'override', 'violation', 'skipped']);
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'log_type']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discipline_logs');
    }
};
