<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_settings', function (Blueprint $table) {
            $table->id();
            
            // Jam Kerja
            $table->time('working_hour_mon_thu_start')->default('08:00:00');
            $table->time('working_hour_mon_thu_end')->default('17:00:00');
            $table->time('working_hour_fri_start')->default('08:00:00');
            $table->time('working_hour_fri_end')->default('17:30:00');
            
            // Overtime Hari Kerja
            $table->time('ot_workday_hour1_start')->default('17:00:00');
            $table->time('ot_workday_hour1_end')->default('18:00:00');
            $table->time('ot_workday_hour2_start')->default('19:00:00');
            $table->time('ot_workday_hour2_end')->nullable();
            $table->time('ot_workday_rest_start')->default('18:00:00');
            $table->time('ot_workday_rest_end')->default('19:00:00');
            $table->decimal('ot_workday_rate', 12, 2)->default(10000.00);
            
            // Overtime Hari Libur
            $table->time('ot_holiday_shift1_start')->default('08:00:00');
            $table->time('ot_holiday_shift1_end')->default('16:00:00');
            $table->time('ot_holiday_shift1_rest_start')->default('12:00:00');
            $table->time('ot_holiday_shift1_rest_end')->default('13:00:00');
            
            $table->time('ot_holiday_shift2_start')->default('16:00:00');
            $table->time('ot_holiday_shift2_end')->nullable();
            $table->time('ot_holiday_shift2_rest_start')->default('18:00:00');
            $table->time('ot_holiday_shift2_rest_end')->default('19:00:00');
            $table->decimal('ot_holiday_rate', 12, 2)->default(10000.00);

            $table->timestamps();
        });

        // Insert default row
        DB::table('payroll_settings')->insert([
            'working_hour_mon_thu_start' => '08:00:00',
            'working_hour_mon_thu_end' => '17:00:00',
            'working_hour_fri_start' => '08:00:00',
            'working_hour_fri_end' => '17:30:00',
            
            'ot_workday_hour1_start' => '17:00:00',
            'ot_workday_hour1_end' => '18:00:00',
            'ot_workday_hour2_start' => '19:00:00',
            'ot_workday_hour2_end' => null,
            'ot_workday_rest_start' => '18:00:00',
            'ot_workday_rest_end' => '19:00:00',
            'ot_workday_rate' => 10000.00,
            
            'ot_holiday_shift1_start' => '08:00:00',
            'ot_holiday_shift1_end' => '16:00:00',
            'ot_holiday_shift1_rest_start' => '12:00:00',
            'ot_holiday_shift1_rest_end' => '13:00:00',
            
            'ot_holiday_shift2_start' => '16:00:00',
            'ot_holiday_shift2_end' => null,
            'ot_holiday_shift2_rest_start' => '18:00:00',
            'ot_holiday_shift2_rest_end' => '19:00:00',
            'ot_holiday_rate' => 10000.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_settings');
    }
};
