<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class HrdEmployeeRoutesTest extends TestCase
{
    public function test_hrd_employee_pages_render_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('hrd.employees.index'))->assertOk();
        $this->actingAs($admin)->get(route('hrd.employees.create'))->assertOk();
    }
}
