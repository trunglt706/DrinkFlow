<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoomOccasion;
use App\Models\Admin;
use App\Models\GlobalUser;
use App\Models\Room;
use App\Models\RoomEventWish;
use App\Models\RoomUser;
use App\Services\Occasion\RoomEventWishWallService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Wishes wall of the room event pages: members write wishes while the event is shown (birthday wishes go to a
 * celebrant), give hearts, delete their own wishes; the room admin hides unsuitable wishes.
 */
class EventWishWallTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Carbon::setTestNow('2026-10-20 10:00:00');
        $this->room = Room::create(['name' => 'Wall Room', 'slug' => 'wall-room', 'status' => 'active', 'timezone' => 'UTC']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function member(string $name, ?string $birthday = null, ?Room $room = null): RoomUser
    {
        $user = GlobalUser::create(['name' => $name, 'email' => strtolower(str_replace(' ', '', $name)).uniqid().'@example.test', 'status' => 'active', 'birthday' => $birthday]);

        return RoomUser::create(['room_id' => ($room ?? $this->room)->id, 'global_user_id' => $user->id, 'display_name' => $name, 'status' => 'active']);
    }

    private function store(string $type): string
    {
        return route('user.event-wishes.store', [$this->room->slug, $type]);
    }

    public function test_members_write_wishes_and_give_hearts_on_the_occasion_wall(): void
    {
        $lan = $this->member('Lan');
        $minh = $this->member('Minh');

        $this->actingAs($lan->globalUser, 'web')->postJson($this->store('womens_day'), ['message' => "  Chúc cả phòng 20/10 vui vẻ!\r\n\r\n\r\n\r\nNhiều niềm vui  "])
            ->assertCreated()->assertJsonPath('wishes.0.author', 'Lan')->assertJsonPath('wishes.0.mine', true)
            ->assertJsonPath('wishes.0.message', "Chúc cả phòng 20/10 vui vẻ!\n\nNhiều niềm vui");
        $wish = RoomEventWish::query()->sole();
        $this->assertSame('2026-10-20', $wish->occurrence->toDateString());

        // Another member sees it on the page and gives a heart (a second click takes it back).
        $this->actingAs($minh->globalUser, 'web')->get(route('user.rooms.occasion', [$this->room->slug, RoomOccasion::WomensDay->slug()]))
            ->assertOk()->assertSee('data-wish-wall', false)->assertSee('Chúc cả phòng 20/10 vui vẻ!');
        $heart = route('user.event-wishes.heart', [$this->room->slug, $wish->id]);
        $this->actingAs($minh->globalUser, 'web')->postJson($heart)->assertOk()->assertExactJson(['hearted' => true, 'hearts' => 1]);
        $this->actingAs($lan->globalUser, 'web')->postJson($heart)->assertOk()->assertExactJson(['hearted' => true, 'hearts' => 2]);
        $this->actingAs($minh->globalUser, 'web')->postJson($heart)->assertOk()->assertExactJson(['hearted' => false, 'hearts' => 1]);
        $this->actingAs($lan->globalUser, 'web')->getJson(route('user.event-wishes.index', [$this->room->slug, 'womens_day']))
            ->assertOk()->assertJsonPath('wishes.0.hearts', 1)->assertJsonPath('wishes.0.hearted', true);

        // Only the author deletes a wish.
        $destroy = route('user.event-wishes.destroy', [$this->room->slug, $wish->id]);
        $this->actingAs($minh->globalUser, 'web')->deleteJson($destroy)->assertNotFound();
        $this->actingAs($lan->globalUser, 'web')->deleteJson($destroy)->assertOk();
        $this->assertDatabaseCount('room_event_wishes', 0);
        $this->assertDatabaseCount('room_event_wish_hearts', 0);
    }

    public function test_wishes_are_validated_limited_and_only_while_the_event_is_shown(): void
    {
        $lan = $this->member('Lan');
        $as = $this->actingAs($lan->globalUser, 'web');

        $as->postJson($this->store('womens_day'), ['message' => '   '])->assertStatus(422)->assertJsonValidationErrors('message');
        $as->postJson($this->store('womens_day'), ['message' => str_repeat('a', RoomEventWishWallService::MAX_LENGTH + 1)])->assertStatus(422)->assertJsonValidationErrors('message');
        // Christmas is not shown on 20/10; unknown types do not match the route.
        $as->postJson($this->store('christmas'), ['message' => 'Merry Christmas'])->assertStatus(422)->assertJsonPath('errors.message.0', __('occasions.wall.closed'));
        $as->postJson($this->store('halloween'), ['message' => 'Boo'])->assertNotFound();
        // Pages need a JSON request.
        $as->post($this->store('womens_day'), ['message' => 'Hi'])->assertNotFound();

        for ($i = 1; $i <= RoomEventWishWallService::MAX_PER_MEMBER; $i++) {
            $as->postJson($this->store('womens_day'), ['message' => "Lời chúc {$i}"])->assertCreated();
        }
        $as->postJson($this->store('womens_day'), ['message' => 'Thêm nữa'])->assertStatus(422)
            ->assertJsonPath('errors.message.0', __('occasions.wall.limit', ['max' => RoomEventWishWallService::MAX_PER_MEMBER]));

        // The next occurrence starts with an empty wall.
        Carbon::setTestNow('2027-10-20 08:00:00');
        Cache::flush();
        $as->getJson(route('user.event-wishes.index', [$this->room->slug, 'womens_day']))->assertOk()->assertJsonCount(0, 'wishes');
        $as->postJson($this->store('womens_day'), ['message' => 'Năm mới'])->assertCreated()->assertJsonCount(1, 'wishes');
    }

    public function test_birthday_wishes_go_to_a_celebrant(): void
    {
        $celebrant = $this->member('Hoa', '1995-10-21');
        $other = $this->member('Tuan', '1990-03-01');
        $writer = $this->member('Lan');
        $as = $this->actingAs($writer->globalUser, 'web');

        $as->get(route('user.rooms.birthday', $this->room->slug))->assertOk()->assertSee('data-wish-wall', false)
            ->assertSee('name="recipient" value="'.$celebrant->user_code.'"', false);

        $as->postJson($this->store('birthday'), ['message' => 'Happy birthday!'])->assertStatus(422)->assertJsonValidationErrors('recipient');
        $as->postJson($this->store('birthday'), ['message' => 'Happy birthday!', 'recipient' => $other->user_code])->assertStatus(422)->assertJsonValidationErrors('recipient');
        $as->postJson($this->store('birthday'), ['message' => 'Sinh nhật vui vẻ Hoa!', 'recipient' => $celebrant->user_code])
            ->assertCreated()->assertJsonPath('wishes.0.recipient', 'Hoa');
        $this->assertDatabaseHas('room_event_wishes', ['event_type' => 'birthday', 'recipient_room_user_id' => $celebrant->id]);
        $this->assertSame('2026-10-21', RoomEventWish::query()->sole()->occurrence->toDateString());
    }

    public function test_admin_hides_a_wish_and_strangers_cannot_reach_the_wall(): void
    {
        $lan = $this->member('Lan');
        $this->actingAs($lan->globalUser, 'web')->postJson($this->store('womens_day'), ['message' => 'Nội dung không phù hợp'])->assertCreated();
        $wish = RoomEventWish::query()->sole();
        $admin = Admin::create(['name' => 'Room Admin', 'email' => 'wall-admin@example.test', 'password' => 'secret123', 'status' => 'active']);
        $admin->rooms()->attach($this->room);
        $moderate = route('admin.events.wall.moderate', [$this->room->slug, $wish->id]);

        $this->actingAs($admin, 'admin')->get(route('admin.events.index', $this->room->slug))
            ->assertOk()->assertSee('data-event-modal="wall-womens_day"', false)->assertSee('Nội dung không phù hợp');
        $this->actingAs($admin, 'admin')->putJson($moderate, ['hidden' => true])->assertOk()->assertJsonPath('hidden', true);
        $this->assertNotNull($wish->fresh()->hidden_at);
        $this->assertSame($admin->id, $wish->fresh()->hidden_by_admin_id);

        // Hidden for members, no heart possible; still listed for the admin.
        $this->actingAs($lan->globalUser, 'web')->getJson(route('user.event-wishes.index', [$this->room->slug, 'womens_day']))->assertJsonCount(0, 'wishes');
        $this->actingAs($lan->globalUser, 'web')->postJson(route('user.event-wishes.heart', [$this->room->slug, $wish->id]))->assertNotFound();
        $this->actingAs($admin, 'admin')->get(route('admin.events.index', $this->room->slug))->assertSee('Nội dung không phù hợp');

        $this->actingAs($admin, 'admin')->putJson($moderate, ['hidden' => false])->assertOk();
        $this->actingAs($lan->globalUser, 'web')->getJson(route('user.event-wishes.index', [$this->room->slug, 'womens_day']))->assertJsonCount(1, 'wishes');

        // Admins of other rooms and members of other rooms are kept out.
        $other = Admin::create(['name' => 'Other', 'email' => 'other-wall-admin@example.test', 'password' => 'secret123', 'status' => 'active']);
        $this->assertContains($this->actingAs($other, 'admin')->putJson($moderate, ['hidden' => true])->getStatusCode(), [401, 403]);
        $this->assertNull($wish->fresh()->hidden_at);
        $stranger = $this->member('Stranger', null, Room::create(['name' => 'Other Room', 'slug' => 'other-room', 'status' => 'active', 'timezone' => 'UTC']));
        $this->assertNotSame(200, $this->actingAs($stranger->globalUser, 'web')->getJson(route('user.event-wishes.index', [$this->room->slug, 'womens_day']))->getStatusCode());
        $this->assertNotSame(201, $this->actingAs($stranger->globalUser, 'web')->postJson($this->store('womens_day'), ['message' => 'Hi'])->getStatusCode());
        $this->assertNotSame(200, $this->actingAs($stranger->globalUser, 'web')->postJson(route('user.event-wishes.heart', [$this->room->slug, $wish->id]))->getStatusCode());
    }
}
