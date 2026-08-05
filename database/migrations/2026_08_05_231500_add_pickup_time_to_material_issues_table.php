<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_issues', function (Blueprint $table) {
            $table->string('pickup_time')->nullable()->after('itr_date');
        });
    }

    public function down(): void
    {
        Schema::table('material_issues', function (Blueprint $table) {
            $table->dropColumn('pickup_time');
        });
    }
};
