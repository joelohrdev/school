<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            // Set once the alert has gone out in a digest email.
            $table->timestamp('notified_at')->nullable()->index()->after('read_at');
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            // SQLite can't drop a column that still has an index on it.
            $table->dropIndex(['notified_at']);
            $table->dropColumn('notified_at');
        });
    }
};
