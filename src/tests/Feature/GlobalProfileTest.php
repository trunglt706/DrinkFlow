<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\OrderStatus;
use App\Models\Campaign;
use App\Models\CampaignItem;
use App\Models\GlobalUser;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentAccount;
use App\Models\Room;
use App\Models\RoomUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

class GlobalProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_accessing_me_profile_redirects_to_google_login(): void
    {
        $response = $this->get('/me/profile');
        $response->assertRedirect('/');

        $responseWithReferer = $this->from('/contact')->get('/me/profile');
        $responseWithReferer->assertRedirect('/contact');
    }

    public function test_authenticated_user_can_view_me_profile_with_real_data(): void
    {
        $user = GlobalUser::create([
            'name' => 'Trung Lê',
            'normalized_name' => 'TRUNG LE',
            'email' => 'trung.lt@company.com',
            'avatar_url' => 'https://example.com/avatar.jpg',
            'status' => 'active',
        ]);

        $room = Room::create([
            'name' => 'Ban Công nghệ & Kỹ thuật số',
            'slug' => 'tech-team',
            'status' => 'active',
        ]);

        RoomUser::create([
            'room_id' => $room->id,
            'global_user_id' => $user->id,
            'user_code' => 'DF-EMP-4089',
            'display_name' => 'Trung Lê',
            'normalized_name' => 'TRUNG LE',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user, 'web')->get('/me/profile');

        $response->assertOk();
        $response->assertSee('Hồ sơ cá nhân &amp; Thiết lập tài khoản', false);
        $response->assertSee('Trung Lê');
        $response->assertSee('trung.lt@company.com');
        $response->assertSee('DF-EMP-4089');
        $response->assertSee('Ban Công nghệ &amp; Kỹ thuật số', false);
        $response->assertSee('Xác thực Google Workspace @company.com');
        $response->assertSee('Managed by IT Admin');
        $response->assertDontSee('Tài khoản nhận tiền hoàn trả');
        $response->assertSee('Ghi chú mặc định cho quán đồ uống');
        $response->assertSee('Thống kê thành viên');
    }

    public function test_profile_shortcuts_and_default_avatar(): void
    {
        $userWithoutAvatar = GlobalUser::create([
            'name' => 'Nguyễn Không Avatar',
            'normalized_name' => 'NGUYEN KHONG AVATAR',
            'email' => 'noavatar@company.com',
            'status' => 'active',
        ]);

        $this->assertStringContainsString('default-avatar.svg', $userWithoutAvatar->avatar_url);

        // User has NO rooms yet: should NOT see payments or statistics shortcuts
        $responseNoRoom = $this->actingAs($userWithoutAvatar, 'web')->get('/me/profile');
        $responseNoRoom->assertOk();
        $responseNoRoom->assertSee(route('user.me.devices'));
        $responseNoRoom->assertDontSee(route('user.me.payments'));
        $responseNoRoom->assertDontSee(route('user.me.statistics'));
        $responseNoRoom->assertSee('default-avatar.svg');

        // User joins a room: should now see payments and statistics shortcuts
        $room = Room::create([
            'name' => 'Phòng Kỹ Thuật',
            'slug' => 'tech-room',
            'status' => 'active',
        ]);

        RoomUser::create([
            'room_id' => $room->id,
            'global_user_id' => $userWithoutAvatar->id,
            'user_code' => 'DF-TECH-01',
            'display_name' => 'Kỹ Thuật Viên',
            'normalized_name' => 'KY THUAT VIEN',
            'status' => 'active',
        ]);

        $responseWithRoom = $this->actingAs($userWithoutAvatar, 'web')->get('/me/profile');
        $responseWithRoom->assertOk();
        $responseWithRoom->assertSee(route('user.me.devices'));
        $responseWithRoom->assertSee(route('user.me.payments'));
        $responseWithRoom->assertSee(route('user.me.statistics'));
    }

    public function test_user_can_update_contact_details_and_preferences(): void
    {
        $user = GlobalUser::create([
            'name' => 'Lê Minh',
            'normalized_name' => 'LE MINH',
            'email' => 'minh.l@company.com',
            'status' => 'active',
        ]);

        $response = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->actingAs($user, 'web')
            ->post('/me/profile', [
            'phone' => '0912 345 678',
            'desk_location' => 'Keangnam Tầng 20 #B12',
            'delivery_location' => 'Bàn làm việc Tầng 20 Keangnam Landmark',
            'sugar' => '30%',
            'ice' => 'Ít đá (30%)',
            'toppings' => ['Trân châu hoàng kim', 'Kem cheese'],
            'note' => 'Không lấy ống hút nhựa',
            'notify_campaign' => true,
            'notify_sound' => false,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $user->refresh();
        $this->assertEquals('0912 345 678', $user->phone);
        $this->assertNotEquals('0912 345 678', \Illuminate\Support\Facades\DB::table('global_users')->where('id', $user->id)->value('phone'));
        $this->assertEquals('Keangnam Tầng 20 #B12', $user->desk_location);
        $this->assertEquals('Bàn làm việc Tầng 20 Keangnam Landmark', $user->delivery_location);
        $this->assertIsArray($user->preferences);
        $this->assertEquals('30%', $user->preferences['sugar']);
        $this->assertEquals('Ít đá (30%)', $user->preferences['ice']);
        $this->assertEquals(['Trân châu hoàng kim', 'Kem cheese'], $user->preferences['toppings']);
        $this->assertEquals('Không lấy ống hút nhựa', $user->preferences['note']);
        $this->assertTrue($user->preferences['notify_campaign']);
        $this->assertFalse($user->preferences['notify_sound']);
    }

    public function test_profile_shows_phone_and_saves_a_markdown_bio_rendered_safely(): void
    {
        $user = GlobalUser::create(['name' => 'Bio User', 'email' => 'bio@company.com', 'status' => 'active', 'phone' => '0987 654 321']);

        $this->actingAs($user, 'web')->get('/me/profile')
            ->assertOk()
            ->assertSee('href="tel:0987654321"', false)
            ->assertSee('data-bio-editor', false)
            ->assertSee(__('global.profile.bio_empty'));

        $this->withoutMiddleware(ValidateCsrfToken::class)->actingAs($user, 'web')
            ->post('/me/profile', ['bio' => "  Thích **trà sữa** <script>alert(1)</script>  "])
            ->assertRedirect()->assertSessionHasNoErrors();

        // Tags are stripped from the input; the Markdown is rendered and any raw HTML left would be escaped.
        $this->assertSame('Thích **trà sữa**', $user->fresh()->bio);
        $this->actingAs($user, 'web')->get('/me/profile')
            ->assertSee('<strong>trà sữa</strong>', false)
            ->assertDontSee('<script>alert(1)</script>', false);
        $this->assertStringContainsString('&lt;script&gt;', \App\Support\Helpers\MarkdownHelper::toSafeHtml('<script>alert(1)</script> [x](javascript:alert(1))'));
        $this->assertStringNotContainsString('javascript:', \App\Support\Helpers\MarkdownHelper::toSafeHtml('[x](javascript:alert(1))'));

        // Clearing the field removes the bio.
        $this->withoutMiddleware(ValidateCsrfToken::class)->actingAs($user, 'web')->post('/me/profile', ['bio' => '   ']);
        $this->assertNull($user->fresh()->bio);
    }

    public function test_bio_is_limited_to_300_words(): void
    {
        $user = GlobalUser::create(['name' => 'Long Bio', 'email' => 'longbio@company.com', 'status' => 'active']);
        $post = fn (string $bio) => $this->withoutMiddleware(ValidateCsrfToken::class)->actingAs($user, 'web')->post('/me/profile', ['bio' => $bio]);

        // Markdown symbols are not words: 300 words stay valid even when formatted.
        $post('**'.implode(' ', array_fill(0, 300, 'từ')).'**')->assertSessionHasNoErrors();
        $this->assertSame(300, \App\Support\Helpers\MarkdownHelper::wordCount((string) $user->fresh()->bio));

        $post(implode(' ', array_fill(0, 301, 'từ')))->assertSessionHasErrors('bio');
        $this->assertSame(300, \App\Support\Helpers\MarkdownHelper::wordCount((string) $user->fresh()->bio));
    }
}
