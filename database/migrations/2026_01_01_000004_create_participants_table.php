<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->string('nickname');
            $table->uuid('token')->unique();
            $table->timestamps();

            $table->unique(['quiz_id', 'nickname']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participants');
    }
};
