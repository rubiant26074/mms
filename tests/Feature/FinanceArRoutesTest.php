<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use Tests\TestCase;

class FinanceArRoutesTest extends TestCase
{
    public function test_ar_index_and_create_render_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('finance.ar.index'))->assertOk();
        $this->actingAs($admin)->get(route('finance.ar.create'))->assertOk();
    }

    public function test_ar_prints_render_when_fixture_exists(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();
        $invoice = Invoice::query()->first();
        if (! $invoice) {
            $this->markTestSkipped('Invoice fixture is not available.');
        }

        $this->actingAs($admin)->get(route('finance.ar.print', $invoice))->assertOk();
        $this->actingAs($admin)->get(route('finance.ar.print_tax', $invoice))->assertOk();
    }
}
