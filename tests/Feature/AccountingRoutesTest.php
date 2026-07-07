<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AccountingRoutesTest extends TestCase
{
    public function test_coa_pages_render_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('accounting.coa.index'))->assertOk();
        $this->actingAs($admin)->get(route('accounting.coa.create'))->assertOk();
        $this->actingAs($admin)->get(route('accounting.journal.index'))->assertOk();
        $this->actingAs($admin)->get(route('accounting.journal.create'))->assertOk();
        $this->actingAs($admin)->get(route('accounting.journal.print'))->assertOk();
        $this->actingAs($admin)->get(route('accounting.ledger.index'))->assertOk();
        $this->actingAs($admin)->get(route('accounting.reports.index'))->assertOk();
        $this->actingAs($admin)->get(route('accounting.reports.print'))->assertOk();
        $this->actingAs($admin)->get(route('accounting.assets.index'))->assertOk();
        $this->actingAs($admin)->get(route('accounting.assets.create'))->assertOk();
        $this->actingAs($admin)->get(route('accounting.assets.print'))->assertOk();
    }
}
