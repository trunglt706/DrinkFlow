<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Superadmin;
use App\Services\Audit\AuditService;
use App\Services\Security\SensitiveDataService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Emails and phone numbers are masked across the Superadmin console (pages and JSON APIs) and revealed one
 * value at a time through an audited, viewer-bound token; the audit log offers a per-entry detail modal.
 */
class SuperadminSensitiveDataTest extends TestCase
{
    use RefreshDatabase;

    private Superadmin $owner;

    private Admin $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->owner = $this->createSuperadmin(['name' => 'Owner', 'email' => 'owner@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $this->agent = Admin::create(['name' => 'Agent Alice', 'email' => 'alice.agent@example.test', 'phone' => '0901234567', 'password' => 'password123', 'status' => 'active']);
    }

    public function test_mask_formats(): void
    {
        $sensitive = app(SensitiveDataService::class);

        $this->assertSame('al***@e***.test', $sensitive->mask('alice.agent@example.test', 'email'));
        $this->assertSame('*******567', $sensitive->mask('0901234567', 'phone'));
        $this->assertSame('', $sensitive->mask(null, 'email'));
    }

    public function test_pages_show_masked_values_with_a_reveal_button_only(): void
    {
        $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.agents.index'))
            ->assertOk()
            ->assertDontSee('alice.agent@example.test')
            ->assertSee('al***@e***.test')
            ->assertSee('data-sensitive-reveal=', false);
        $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.agents.show', $this->agent))
            ->assertOk()
            ->assertDontSee('alice.agent@example.test')
            ->assertDontSee('0901234567')
            ->assertSee('*******567');
    }

    public function test_json_apis_never_return_raw_values(): void
    {
        $response = $this->actingAs($this->owner, 'superadmin')->getJson(route('superadmin.admins.show', $this->agent))->assertOk();

        $this->assertStringNotContainsString('alice.agent@example.test', $response->getContent());
        $this->assertStringNotContainsString('0901234567', $response->getContent());
        $this->assertSame('al***@e***.test', $response->json('data.email'));
        $this->assertNotEmpty($response->json('data.email_reveal'));
        $this->assertNotEmpty($response->json('data.phone_reveal'));
    }

    public function test_reveal_returns_the_value_to_its_viewer_and_audits_it(): void
    {
        $token = $this->actingAs($this->owner, 'superadmin')->getJson(route('superadmin.admins.show', $this->agent))->json('data.email_reveal');

        $this->actingAs($this->owner, 'superadmin')->postJson(route('superadmin.sensitive.reveal'), ['token' => $token])
            ->assertOk()
            ->assertJsonPath('data.value', 'alice.agent@example.test');

        $log = AuditLog::query()->where('event', 'sensitive.revealed')->sole();
        $this->assertSame('email', $log->metadata['type']);
        $this->assertSame('al***@e***.test', $log->metadata['masked']);
        $this->assertStringNotContainsString('alice.agent@example.test', json_encode($log->toArray()));
    }

    public function test_reveal_refuses_other_viewers_tampered_and_expired_tokens(): void
    {
        $token = app(SensitiveDataService::class)->token('alice.agent@example.test', 'email', $this->owner);
        $other = $this->createSuperadmin(['name' => 'Other', 'email' => 'other@drinkflow.test', 'password' => 'password123', 'status' => 'active']);

        $this->actingAs($other, 'superadmin')->postJson(route('superadmin.sensitive.reveal'), ['token' => $token])->assertNotFound();
        $middle = intdiv(strlen($token), 2);
        $tampered = substr_replace($token, $token[$middle] === 'A' ? 'B' : 'A', $middle, 1);
        $this->actingAs($this->owner, 'superadmin')->postJson(route('superadmin.sensitive.reveal'), ['token' => $tampered])->assertNotFound();
        $this->actingAs($this->owner, 'superadmin')->postJson(route('superadmin.sensitive.reveal'), ['token' => 'not-a-token'])->assertNotFound();
        $this->actingAs($this->owner, 'superadmin')->postJson(route('superadmin.sensitive.reveal'), [])->assertUnprocessable();

        $this->travel(SensitiveDataService::TOKEN_TTL_MINUTES + 1)->minutes();
        $this->actingAs($this->owner, 'superadmin')->postJson(route('superadmin.sensitive.reveal'), ['token' => $token])->assertNotFound();
        $this->assertSame(0, AuditLog::query()->where('event', 'sensitive.revealed')->count());
    }

    public function test_reveal_needs_a_superadmin_session(): void
    {
        $token = app(SensitiveDataService::class)->token('alice.agent@example.test', 'email', $this->owner);

        $this->postJson(route('superadmin.sensitive.reveal'), ['token' => $token])->assertUnauthorized();
        $this->actingAs($this->agent, 'admin')->postJson(route('superadmin.sensitive.reveal'), ['token' => $token])->assertUnauthorized();
    }

    public function test_profile_shells_only_hand_the_key_to_their_page_script(): void
    {
        $room = \App\Models\Room::create(['name' => 'Shell room', 'slug' => 'shell-room', 'status' => 'active']);

        $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.admins.detail.page', $this->agent))
            ->assertOk()
            ->assertSee('&quot;admin_id&quot;:&quot;'.$this->agent->id.'&quot;', false)
            ->assertDontSee('alice.agent@example.test');
        $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.rooms.detail.page', $room))
            ->assertOk()
            ->assertSee('&quot;room_id&quot;:&quot;shell-room&quot;', false);
    }

    public function test_audit_log_rows_open_a_detail_modal(): void
    {
        $this->actingAs($this->owner, 'superadmin');
        app(AuditService::class)->record('package.updated', 'package', 7, null, ['monthly_price' => 100], ['monthly_price' => 200]);

        $this->get(route('superadmin.audit.page'))
            ->assertOk()
            ->assertSee('id="audit-detail-modal"', false)
            ->assertSee('data-audit-detail=', false)
            ->assertSee('monthly_price', false)
            ->assertSee('data-sa-page="audit"', false);
    }
}
