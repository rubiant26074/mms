<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class WarehouseRoutesTest extends TestCase
{
    public function test_receipt_pages_render_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('warehouse.receipts.index'))->assertOk();
        $this->actingAs($admin)->get(route('warehouse.receipts.create'))->assertOk();
    }

    public function test_legacy_receive_url_redirects_to_native_route(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get('/index.php?page=whse-receive')
            ->assertRedirect(route('warehouse.receipts.index'));
    }
}
