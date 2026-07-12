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
        \Illuminate\Support\Facades\DB::table('permissions')->insertOrIgnore([
            'permission_name' => 'Manage Cash/Chasier',
            'permission_slug' => 'fin_cash_manage',
            'description' => 'Mengelola transaksi kas (Input, Edit, Delete, Post/Unpost)',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::table('permissions')->where('permission_slug', 'fin_cash_manage')->delete();
    }
};
