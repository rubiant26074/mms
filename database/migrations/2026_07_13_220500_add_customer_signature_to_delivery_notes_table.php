<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->string('customer_signature_path', 255)->nullable()->after('approved_by');
            $table->string('received_by_name', 100)->nullable()->after('customer_signature_path');
            $table->timestamp('received_at')->nullable()->after('received_by_name');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropColumn(['customer_signature_path', 'received_by_name', 'received_at']);
        });
    }
};
