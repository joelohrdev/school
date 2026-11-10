<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quarter dates entered by hand, used only when PowerSchool doesn't send
     * quarter reporting terms of its own.
     */
    public function up(): void
    {
        Schema::create('school_quarters', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_quarters');
    }
};
