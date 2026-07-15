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
        $role = \Illuminate\Support\Facades\DB::table('roles')->where('role_slug', 'hrd1')->first();
        if (!$role) {
            $roleId = \Illuminate\Support\Facades\DB::table('roles')->insertGetId([
                'role_name' => 'Staff HRD (HRD1)',
                'role_slug' => 'hrd1',
                'description' => 'Staff HRD Tanpa Akses Gaji',
            ]);
        } else {
            $roleId = $role->id;
        }

        $permissionIds = \Illuminate\Support\Facades\DB::table('permissions')
            ->whereIn('permission_slug', ['hrd_view', 'hrd_employee_manage', 'hrd_attendance_view'])
            ->pluck('id');

        foreach ($permissionIds as $permId) {
            \Illuminate\Support\Facades\DB::table('role_permissions')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permId,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $role = \Illuminate\Support\Facades\DB::table('roles')->where('role_slug', 'hrd1')->first();
        if ($role) {
            \Illuminate\Support\Facades\DB::table('role_permissions')->where('role_id', $role->id)->delete();
            \Illuminate\Support\Facades\DB::table('roles')->where('id', $role->id)->delete();
        }
    }
};
