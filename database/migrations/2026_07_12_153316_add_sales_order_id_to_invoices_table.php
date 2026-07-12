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
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('delivery_note_id')->nullable()->change();
            if (! Schema::hasColumn('invoices', 'sales_order_id')) {
                $table->unsignedBigInteger('sales_order_id')->nullable()->after('delivery_note_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'sales_order_id')) {
                $table->dropColumn('sales_order_id');
            }
        });
    }
};
