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
        Schema::table('customers', function (Blueprint $table) {
            $table->string('sales_name')->nullable()->after('pic');
        });

        // Sinkronisasi data lama: salin nama creator ke sales_name
        Illuminate\Support\Facades\DB::table('customers')
            ->join('users', 'customers.created_by', '=', 'users.id')
            ->whereNull('customers.sales_name')
            ->update([
                'customers.sales_name' => Illuminate\Support\Facades\DB::raw('COALESCE(users.fullname, users.username)')
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('sales_name');
        });
    }
};
