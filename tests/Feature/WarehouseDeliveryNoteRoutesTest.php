<?php

namespace Tests\Feature;

use App\Models\DeliveryNote;
use App\Models\SalesOrder;
use App\Models\User;
use Tests\TestCase;

class WarehouseDeliveryNoteRoutesTest extends TestCase
{
    public function test_delivery_note_index_and_create_render_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('warehouse.delivery_notes.index'))->assertOk();
        $this->actingAs($admin)->get(route('warehouse.delivery_notes.create'))->assertOk();
    }

    public function test_delivery_note_print_renders_when_fixture_exists(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();
        $note = DeliveryNote::query()->first();
        if (! $note) {
            $this->markTestSkipped('Delivery note fixture is not available.');
        }

        $this->actingAs($admin)->get(route('warehouse.delivery_notes.print', $note))->assertOk();
    }

    public function test_delivery_note_so_items_endpoint_returns_json_when_order_exists(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();
        $order = SalesOrder::query()->first();
        if (! $order) {
            $this->markTestSkipped('Sales order fixture is not available.');
        }

        $this->actingAs($admin)
            ->get(route('warehouse.delivery_notes.so_items', $order))
            ->assertOk()
            ->assertJsonIsArray();
    }

    public function test_legacy_delivery_note_url_redirects_to_native_route(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get('/index.php?page=whse-sj')
            ->assertRedirect(route('warehouse.delivery_notes.index'));
    }
}
