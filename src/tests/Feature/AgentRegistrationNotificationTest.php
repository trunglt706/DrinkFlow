<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PermissionScope;
use App\Events\SuperadminNotificationCreated;
use App\Models\Package;
use App\Models\PermissionRecord;
use App\Models\Superadmin;
use App\Models\SuperadminNotification;
use App\Services\Admin\AgentRegistrationNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A self-service Agent registration notifies (inbox + realtime) only the Superadmins who can review it.
 */
class AgentRegistrationNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();
        config(['captcha.disable' => true]);
        Mail::fake();
        $this->package = Package::create(['code' => 'starter', 'name' => 'Starter', 'monthly_price' => 0, 'room_limit' => 1, 'status' => 'active']);
    }

    /**
     * Active Superadmin holding the given permissions.
     *
     * @param string $email Email.
     * @param array<string, PermissionScope> $grants Permission key => scope.
     * @param string $status Account status.
     * @return Superadmin Superadmin.
     */
    private function superadminWith(string $email, array $grants, string $status = 'active'): Superadmin
    {
        $superadmin = Superadmin::create(['name' => 'SA '.$email, 'email' => $email, 'password' => 'password123', 'status' => $status]);
        foreach ($grants as $key => $scope) {
            $superadmin->permissions()->attach(PermissionRecord::query()->where('key', $key)->value('id'), ['scope' => $scope->value]);
        }

        return $superadmin;
    }

    private function register(): void
    {
        $this->post(route('admin.register'), [
            'name' => 'Nguyen Van A',
            'company' => 'Tea Corp',
            'email' => 'agent@example.test',
            'phone' => '+84 901 234 567',
            'package_id' => $this->package->id,
            'period_quantity' => 6,
            'period_unit' => 'month',
        ])->assertRedirect(route('admin.register.pending'));
    }

    public function test_registration_notifies_full_access_and_approvers_only(): void
    {
        Event::fake([SuperadminNotificationCreated::class]);
        $owner = $this->createSuperadmin(['name' => 'Owner', 'email' => 'owner@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $approver = $this->superadminWith('approver@drinkflow.test', ['agent.approve' => PermissionScope::Managed]);
        $viewer = $this->superadminWith('viewer@drinkflow.test', ['agent.view' => PermissionScope::All]);
        $suspended = $this->superadminWith('suspended@drinkflow.test', ['agent.approve' => PermissionScope::All], 'suspended');

        $this->register();

        $recipients = SuperadminNotification::query()->where('type', AgentRegistrationNotifier::TYPE)->pluck('superadmin_id')->sort()->values()->all();
        $this->assertSame([$owner->id, $approver->id], array_map('intval', $recipients));
        $this->assertNotContains($viewer->id, $recipients);
        $this->assertNotContains($suspended->id, $recipients);

        // One realtime publication per recipient, each on that account's own notification.
        Event::assertDispatchedTimes(SuperadminNotificationCreated::class, 2);

        $notification = SuperadminNotification::query()->where('superadmin_id', $approver->id)->firstOrFail();
        $this->assertSame('agent@example.test', $notification->data['email']);
        $this->assertStringEndsWith('/agents/registrations/'.$notification->data['admin_id'], $notification->data['url']);
    }

    public function test_bell_links_to_the_registration_review_page(): void
    {
        $owner = $this->createSuperadmin(['name' => 'Owner', 'email' => 'owner@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $this->register();
        $notification = SuperadminNotification::query()->where('superadmin_id', $owner->id)->firstOrFail();

        $this->actingAs($owner, 'superadmin')
            ->get(route('superadmin.notifications.page'))
            ->assertOk()
            ->assertSee('data-link="'.$notification->data['url'].'"', false)
            ->assertSee($notification->title);
    }

    public function test_register_page_renders_the_centered_form(): void
    {
        $this->get(route('admin.register.page'))
            ->assertOk()
            ->assertSee(__('platform.registration.section_info'))
            ->assertSee(__('platform.registration.section_package'))
            ->assertSee(__('platform.registration.price_free'))
            ->assertSee('id="register-period-quantity"', false)
            ->assertDontSee('id="toggle-admin-password"', false);
    }

    public function test_first_package_is_preselected(): void
    {
        $business = Package::create(['code' => 'business', 'name' => 'Business', 'monthly_price' => 100000, 'room_limit' => 10, 'status' => 'active']);

        $html = $this->get(route('admin.register.page'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/value="'.$this->package->id.'"[^>]*\bchecked\b/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="'.$business->id.'"[^>]*\bchecked\b/', $html);
    }

    public function test_production_shows_and_requires_the_captcha(): void
    {
        config(['captcha.disable' => false]);
        $this->app['env'] = 'production';

        $this->get(route('admin.register.page'))->assertOk()
            ->assertSee('id="admin-captcha"', false)
            ->assertSee(__('platform.registration.field_captcha'));

        // Back to `testing` for the POST (CSRF is only skipped there); it is not local either, so the captcha stays required.
        $this->app['env'] = 'testing';
        $this->post(route('admin.register'), [
            'name' => 'Spam Bot', 'email' => 'bot@example.test', 'phone' => '+84 901 234 567',
            'package_id' => $this->package->id, 'period_quantity' => 6, 'period_unit' => 'month',
        ])->assertSessionHasErrors('captcha');
        $this->assertDatabaseMissing('admins', ['email' => 'bot@example.test']);
    }

    public function test_local_environment_hides_the_captcha(): void
    {
        config(['captcha.disable' => false]);
        $this->app['env'] = 'local';

        $this->get(route('admin.register.page'))->assertOk()->assertDontSee('id="admin-captcha"', false);
    }
}
