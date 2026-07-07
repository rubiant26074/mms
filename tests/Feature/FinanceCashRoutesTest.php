<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class FinanceCashRoutesTest extends TestCase
{
    public function test_cash_pages_render_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('finance.cash.index'))->assertOk();
        $this->actingAs($admin)->get(route('finance.cash.create'))->assertOk();
    }
}
