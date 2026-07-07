<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class HrdAttendanceRoutesTest extends TestCase
{
    public function test_hrd_attendance_page_renders_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('hrd.attendance.index'))->assertOk();
    }
}
