<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class FinanceRoutesTest extends TestCase
{
    public function test_tax_pages_render_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('finance.tax.index'))->assertOk();
        $this->actingAs($admin)->get(route('finance.tax.print'))->assertOk();
    }
}
