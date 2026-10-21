<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\DebtStatus;
use App\Enums\NotificationType;
use App\Enums\RoomUserStatus;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\Debt;
use App\Models\GlobalUser;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Room;
use App\Models\RoomSetting;
use App\Models\RoomUser;
use App\Models\UserNotification;
use App\Services\Debt\DebtAutoReminderService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tiered automatic debt reminders (1, 3 and 7 days) and the debtor's snooze.
 */
class DebtAutoReminderTest extends TestCase
{
    use RefreshDatabase;

    private const TZ = 'Asia/Ho_Chi_Minh';

    private Room $room;

    private RoomUser $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->room = Room::create(['name' => 'Reminder Room', 'slug' => 'reminder-room', 'status' => 'active', 'timezone' => self::TZ]);
        $this->member = $this->member('Tran Thi D', 'ttd@example.test');
    }

    private function member(string $name, string $email): RoomUser
    {
        $user = GlobalUser::create(['name' => $name, 'normalized_name' => mb_strtoupper($name), 'email' => $email, 'status' => 'active']);

        return RoomUser::create([
            'room_id' => $this->room->id,
            'global_user_id' => $user->id,
            'user_code' => 'REM-'.$user->id,
            'display_name' => $name,
            'normalized_name' => mb_strtoupper($name),
            'status' => 'active',
        ]);
    }

    /**
     * Create an outstanding campaign debt at the given local time of the room.
     */
    private function debt(string $createdAtLocal, ?RoomUser $member = null, DebtStatus $status = DebtStatus::Unpaid): Debt
    {
        $member ??= $this->member;
        $this->travelTo(Carbon::parse($createdAtLocal, self::TZ));
        $account = PaymentAccount::firstOrCreate(
            ['room_id' => $this->room->id, 'account_number' => '0011223344'],
            ['bank_code' => 'VCB', 'bank_name' => 'Vietcombank', 'account_name' => 'DRINKFLOW', 'is_default' => true, 'status' => 'active']
        );
        $campaign = Campaign::create([
            'room_id' => $this->room->id,
            'name' => 'Tra sua '.$createdAtLocal,
            'restaurant' => 'Cafe',
            'status' => CampaignStatus::Closed,
            'payment_account_id' => $account->id,
        ]);

        return Debt::create([
            'room_id' => $this->room->id,
            'campaign_id' => $campaign->id,
            'room_user_id' => $member->id,
            'original_amount' => 45000,
            'sponsor_amount' => 0,
            'paid_amount' => 0,
            'remaining_amount' => 45000,
            'status' => $status,
        ]);
    }

    /**
     * Run the scheduler command at the given local time of the room.
     */
    private function runAt(string $local): void
    {
        $this->travelTo(Carbon::parse($local, self::TZ));
        $this->artisan('drinkflow:remind-debts')->assertSuccessful();
    }

    /** @return list<int> Stages notified to the member, oldest first. */
    private function notifiedStages(?RoomUser $member = null): array
    {
        return UserNotification::query()
            ->where('room_user_id', ($member ?? $this->member)->id)
            ->where('type', NotificationType::DebtReminder->value)
            ->orderBy('id')->get()
            ->map(fn (UserNotification $notification): int => (int) $notification->data['stage'])
            ->all();
    }

    public function test_reminders_are_sent_once_per_stage_after_one_three_and_seven_days(): void
    {
        $debt = $this->debt('2026-10-01 10:00');

        $this->runAt('2026-10-01 23:00');
        $this->runAt('2026-10-02 08:59');
        $this->assertSame([], $this->notifiedStages(), 'Nothing before day 1 / before 9:00.');

        $this->runAt('2026-10-02 09:15');
        $this->runAt('2026-10-02 15:15');
        $this->assertSame([1], $this->notifiedStages());

        $this->runAt('2026-10-03 09:15');
        $this->assertSame([1], $this->notifiedStages());
        $this->runAt('2026-10-04 09:15');
        $this->assertSame([1, 3], $this->notifiedStages());

        $this->runAt('2026-10-08 09:15');
        $this->runAt('2026-10-20 09:15');
        $this->assertSame([1, 3, 7], $this->notifiedStages());

        $notification = UserNotification::query()->where('room_user_id', $this->member->id)->latest('id')->first();
        $this->assertSame(route('user.debts.index', ['room' => $this->room, 'debt' => $debt->code]), $notification->link);
        $this->assertSame($debt->code, $notification->data['debt_code']);
        $this->assertSame(3, DB::table('debt_reminders')->where('debt_id', $debt->id)->count());
    }

    public function test_a_debt_already_past_several_stages_only_gets_the_latest_one(): void
    {
        $this->debt('2026-09-01 10:00');

        $this->runAt('2026-10-02 09:15');
        $this->runAt('2026-10-03 09:15');

        $this->assertSame([7], $this->notifiedStages());
    }

    public function test_settled_pending_cancelled_and_inactive_debts_are_not_reminded(): void
    {
        $this->debt('2026-10-01 10:00', status: DebtStatus::Pending);
        $paid = $this->debt('2026-10-01 10:00');
        DB::table('debts')->where('id', $paid->id)->update(['status' => DebtStatus::Paid->value, 'remaining_amount' => 0]);

        $left = $this->member('Left Member', 'left@example.test');
        $this->debt('2026-10-01 10:00', $left);
        $left->update(['status' => RoomUserStatus::Removed]);

        // Backed only by a cancelled order: hidden from the debts page, so never reminded.
        $cancelled = $this->debt('2026-10-01 10:00');
        Order::query()->create([
            'room_id' => $this->room->id,
            'campaign_id' => $cancelled->campaign_id,
            'room_user_id' => $this->member->id,
            'subtotal' => 45000,
            'final_amount' => 45000,
            'status' => 'cancelled',
        ]);

        $this->runAt('2026-10-02 09:15');

        $this->assertSame([], $this->notifiedStages());
        $this->assertSame([], $this->notifiedStages($left));
    }

    public function test_rooms_that_turned_reminders_off_are_skipped(): void
    {
        $this->debt('2026-10-01 10:00');
        RoomSetting::create(['room_id' => $this->room->id, 'key' => DebtAutoReminderService::SETTING_KEY, 'value' => '0', 'type' => RoomSetting::TYPE_BOOLEAN]);

        $this->runAt('2026-10-02 09:15');

        $this->assertSame([], $this->notifiedStages());
    }

    public function test_member_snoozes_reminders_and_they_resume_afterwards(): void
    {
        $debt = $this->debt('2026-10-01 10:00');
        $this->travelTo(Carbon::parse('2026-10-01 18:00', self::TZ));

        $this->actingAs($this->member->globalUser, 'web')
            ->postJson(route('user.debts.snooze-reminders', $this->room), ['debt_id' => $debt->id])
            ->assertOk()
            ->assertJsonPath('snooze.until', '04/10/2026 18:00')
            ->assertJsonPath('snooze.remaining', DebtAutoReminderService::MAX_SNOOZES - 1);

        $this->runAt('2026-10-02 09:15');
        $this->runAt('2026-10-04 09:15');
        $this->assertSame([], $this->notifiedStages(), 'Snoozed until 04/10 18:00.');

        $this->runAt('2026-10-05 09:15');
        $this->assertSame([3], $this->notifiedStages());
    }

    public function test_snooze_is_limited_and_only_for_the_members_own_outstanding_debts(): void
    {
        $debt = $this->debt('2026-10-01 10:00');
        $other = $this->debt('2026-10-01 10:00', $this->member('Other', 'other@example.test'));
        $route = route('user.debts.snooze-reminders', $this->room);
        $user = $this->member->globalUser;

        for ($i = 0; $i < DebtAutoReminderService::MAX_SNOOZES; $i++) {
            $this->actingAs($user, 'web')->postJson($route, ['debt_id' => $debt->id])->assertOk();
        }
        $this->actingAs($user, 'web')->postJson($route, ['debt_id' => $debt->id])->assertStatus(422)->assertJsonValidationErrors('debt_id');
        $this->assertSame(DebtAutoReminderService::MAX_SNOOZES, $debt->fresh()->reminder_snooze_count);

        $this->actingAs($user, 'web')->postJson($route, ['debt_id' => $other->id])->assertStatus(422);
        $this->assertNull($other->fresh()->reminder_snoozed_until);
    }

    public function test_debts_page_shows_the_snooze_option(): void
    {
        $this->debt('2026-10-01 10:00');

        $this->actingAs($this->member->globalUser, 'web')
            ->get(route('user.debts.index', $this->room))
            ->assertOk()
            ->assertSee('data-debt-snooze', false)
            ->assertSee(__('debt_reminder.snooze_button', ['days' => DebtAutoReminderService::SNOOZE_DAYS]));
    }

    public function test_admin_turns_the_reminders_off_in_room_settings(): void
    {
        $admin = Admin::create(['name' => 'Room Admin', 'email' => 'reminder-admin@example.test', 'password' => 'secret123', 'status' => 'active']);
        $admin->rooms()->attach($this->room);

        $this->actingAs($admin, 'admin')
            ->patchJson(route('admin.settings.update', $this->room), [DebtAutoReminderService::SETTING_KEY => false])
            ->assertOk()
            ->assertJsonPath('data.'.DebtAutoReminderService::SETTING_KEY, false);

        $this->assertFalse(app(DebtAutoReminderService::class)->isEnabled($this->room));
    }
}
