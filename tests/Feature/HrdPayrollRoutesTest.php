<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class HrdPayrollRoutesTest extends TestCase
{
    public function test_hrd_payroll_pages_render_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('hrd.payroll.index'))->assertOk();
        $this->actingAs($admin)->get(route('hrd.payroll.create'))->assertOk();
    }
}
