<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('production_assignments') && ! Schema::hasColumn('production_assignments', 'machine_id')) {
            Schema::table('production_assignments', function (Blueprint $table): void {
                $table->unsignedBigInteger('machine_id')->nullable()->after('operator_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('production_assignments') && Schema::hasColumn('production_assignments', 'machine_id')) {
            Schema::table('production_assignments', function (Blueprint $table): void {
                $table->dropColumn('machine_id');
            });
        }
    }
};
