<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            // Set once a missing-work alert has gone out in its own email, so it's sent once.
            $table->timestamp('alert_emailed_at')->nullable()->after('notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropColumn('alert_emailed_at');
        });
    }
};
