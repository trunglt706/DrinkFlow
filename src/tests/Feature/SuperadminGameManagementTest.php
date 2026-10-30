<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PermissionScope;
use App\Models\AuditLog;
use App\Models\Game;
use App\Models\GameRuleSet;
use App\Models\GlobalUser;
use App\Models\PermissionRecord;
use App\Models\Superadmin;
use Database\Seeders\GameSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Superadmins manage the games of the /me "Trò chơi" catalogue; the /me portal menu links to it.
 */
class SuperadminGameManagementTest extends TestCase
{
    use RefreshDatabase;

    private Superadmin $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GameSeeder::class);
        $this->owner = $this->createSuperadmin(['name' => 'Owner', 'email' => 'owner@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
    }

    private function superadminWith(array $keys): Superadmin
    {
        $superadmin = Superadmin::create(['name' => 'Limited', 'email' => 'limited@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        foreach ($keys as $key) {
            $superadmin->permissions()->attach(PermissionRecord::query()->where('key', $key)->value('id'), ['scope' => PermissionScope::All->value]);
        }

        return $superadmin;
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Cờ cá ngựa',
            'slug' => 'co-ca-ngua',
            'description' => 'Đua ngựa cùng nhóm',
            'icon' => 'casino',
            'sort_order' => 2,
            'is_active' => '1',
        ];
    }

    public function test_superadmin_lists_creates_updates_and_deletes_games(): void
    {
        $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.games.index'))
            ->assertOk()->assertSee('Game vui')->assertSee(__('games.admin.create'))
            ->assertSee('https://fonts.google.com/icons', false);

        $this->actingAs($this->owner, 'superadmin')->post(route('superadmin.games.store'), $this->payload())
            ->assertRedirect(route('superadmin.games.index'));
        $game = Game::where('slug', 'co-ca-ngua')->firstOrFail();
        $this->assertTrue($game->is_active);
        $this->assertSame('casino', $game->icon);

        // Slug stays unique.
        $this->actingAs($this->owner, 'superadmin')->post(route('superadmin.games.store'), $this->payload(['name' => 'Khác']))
            ->assertSessionHasErrors('slug');

        $this->actingAs($this->owner, 'superadmin')
            ->put(route('superadmin.games.update', $game), $this->payload(['name' => 'Cờ cá ngựa 2', 'is_active' => null]))
            ->assertRedirect(route('superadmin.games.show', $game));
        $game->refresh();
        $this->assertSame('Cờ cá ngựa 2', $game->name);
        $this->assertFalse($game->is_active);

        $this->actingAs($this->owner, 'superadmin')->delete(route('superadmin.games.destroy', $game))
            ->assertRedirect(route('superadmin.games.index'));
        $this->assertNull(Game::find($game->id));
        $this->assertSame(1, AuditLog::where('event', 'game.deleted')->count());
    }

    public function test_game_in_use_cannot_be_deleted(): void
    {
        $game = Game::where('slug', Game::SLUG_FUN)->firstOrFail();
        $user = GlobalUser::create(['name' => 'U', 'normalized_name' => 'U', 'email' => 'u@company.com', 'status' => 'active']);
        GameRuleSet::create(['game_id' => $game->id, 'owner_id' => $user->id, 'name' => 'Luật']);

        $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.games.show', $game))
            ->assertOk()->assertSee(__('games.admin.in_use'))->assertDontSee('data-delete-game', false);
        $this->actingAs($this->owner, 'superadmin')->from(route('superadmin.games.show', $game))
            ->delete(route('superadmin.games.destroy', $game))
            ->assertSessionHasErrors('game');
        $this->assertNotNull(Game::find($game->id));
    }

    public function test_permissions_are_enforced(): void
    {
        $viewer = $this->superadminWith(['game.view']);
        $this->actingAs($viewer, 'superadmin')->get(route('superadmin.games.index'))->assertOk()->assertDontSee(__('games.admin.create'));
        $this->actingAs($viewer, 'superadmin')->post(route('superadmin.games.store'), $this->payload())->assertForbidden();

        $nobody = Superadmin::create(['name' => 'None', 'email' => 'none@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $this->actingAs($nobody, 'superadmin')->get(route('superadmin.games.index'))->assertForbidden();
    }

    public function test_inactive_game_is_hidden_from_users_and_menu_links_to_games(): void
    {
        $user = GlobalUser::create(['name' => 'U', 'normalized_name' => 'U', 'email' => 'u@company.com', 'status' => 'active']);
        $this->actingAs($user, 'web')->get(route('user.me.games'))
            ->assertOk()->assertSee('data-nav-games', false)->assertSee('Game vui');

        Game::where('slug', Game::SLUG_FUN)->update(['is_active' => false]);
        $this->actingAs($user, 'web')->get(route('user.me.games'))->assertOk()->assertDontSee('Game vui');
        $this->actingAs($user, 'web')->get(route('user.me.games.show', Game::SLUG_FUN))->assertNotFound();
    }
}
