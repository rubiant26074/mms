<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->json('allowance_details')->nullable()->after('allowance_total');
            $table->json('deduction_details')->nullable()->after('deduction_total');
            $table->string('attendance_mode')->default('auto')->after('total_attendance');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn(['allowance_details', 'deduction_details', 'attendance_mode']);
        });
    }
};
