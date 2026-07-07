<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class WarehouseBatchExpiryRoutesTest extends TestCase
{
    public function test_batch_expiry_index_and_print_render_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('warehouse.batch_expiry.index'))->assertOk();
        $this->actingAs($admin)->get(route('warehouse.batch_expiry.print'))->assertOk();
    }

    public function test_legacy_batch_expiry_url_redirects_to_native_route(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get('/index.php?page=whse-batch-expiry')
            ->assertRedirect(route('warehouse.batch_expiry.index'));
    }
}
