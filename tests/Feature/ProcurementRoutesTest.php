<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ProcurementRoutesTest extends TestCase
{
    public function test_supplier_pages_render_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('procurement.suppliers.index'))->assertOk();
        $this->actingAs($admin)->get(route('procurement.suppliers.create'))->assertOk();
    }

    public function test_purchase_order_pages_render_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('procurement.orders.index'))->assertOk();
        $this->actingAs($admin)->get(route('procurement.orders.create'))->assertOk();
    }

    public function test_rfq_pages_render_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('procurement.rfqs.index'))->assertOk();
        $this->actingAs($admin)->get(route('procurement.rfqs.create'))->assertOk();
    }

    public function test_vendor_rating_pages_render_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('procurement.vendor_ratings.index'))->assertOk();
        $this->actingAs($admin)->get(route('procurement.vendor_ratings.create'))->assertOk();
        $this->actingAs($admin)->get(route('procurement.vendor_ratings.print'))->assertOk();
    }



}
