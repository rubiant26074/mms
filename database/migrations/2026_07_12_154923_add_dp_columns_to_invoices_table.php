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
            if (! Schema::hasColumn('invoices', 'invoice_type')) {
                $table->string('invoice_type', 20)->default('normal')->after('status');
            }
            if (! Schema::hasColumn('invoices', 'dp_percent')) {
                $table->decimal('dp_percent', 5, 2)->nullable()->after('invoice_type');
            }
            if (! Schema::hasColumn('invoices', 'dp_amount')) {
                $table->decimal('dp_amount', 15, 2)->default(0)->after('dp_percent');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['invoice_type', 'dp_percent', 'dp_amount']);
        });
    }
};
