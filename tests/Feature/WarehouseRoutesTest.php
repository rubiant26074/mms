<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class WarehouseRoutesTest extends TestCase
{
    public function test_receipt_pages_render_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('warehouse.items.index'))->assertOk();
        $this->actingAs($admin)->get(route('warehouse.items.create'))->assertOk();
        $this->actingAs($admin)->get(route('warehouse.receipts.index'))->assertOk();
        $this->actingAs($admin)->get(route('warehouse.receipts.create'))->assertOk();
    }
}
