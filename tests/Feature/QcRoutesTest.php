<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class QcRoutesTest extends TestCase
{
    public function test_incoming_page_renders_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('qc.incoming.index'))->assertOk();
    }

    public function test_legacy_incoming_url_redirects_to_native_route(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get('/index.php?page=qc-incoming')
            ->assertRedirect(route('qc.incoming.index'));
    }
}
