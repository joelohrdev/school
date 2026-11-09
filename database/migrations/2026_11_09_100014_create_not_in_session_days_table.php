<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('not_in_session_days', function (Blueprint $table) {
            $table->id();
            // 0 = applies to every school.
            $table->unsignedInteger('school_number')->default(0);
            $table->date('date');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['school_number', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('not_in_session_days');
    }
};
