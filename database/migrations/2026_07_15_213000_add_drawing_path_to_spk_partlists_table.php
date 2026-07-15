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
        if (Schema::hasTable('spk_partlists') && !Schema::hasColumn('spk_partlists', 'drawing_path')) {
            Schema::table('spk_partlists', function (Blueprint $table) {
                $table->string('drawing_path', 255)->nullable()->after('notes');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('spk_partlists') && Schema::hasColumn('spk_partlists', 'drawing_path')) {
            Schema::table('spk_partlists', function (Blueprint $table) {
                $table->dropColumn('drawing_path');
            });
        }
    }
};
