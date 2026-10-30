<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Game;
use Illuminate\Database\Seeder;

/**
 * Catalogue of the /me "Trò chơi" games. Idempotent: run it again to add or refresh games.
 */
class GameSeeder extends Seeder
{
    /**
     * Seed the default games.
     *
     * @return void
     */
    public function run(): void
    {
        Game::updateOrCreate(
            ['slug' => Game::SLUG_FUN],
            [
                'name' => 'Game vui',
                'description' => 'Ghi nhận thắng/thua, tính điểm và bảng xếp hạng cho nhóm chơi.',
                'icon' => 'sports_esports',
                'is_active' => true,
                'sort_order' => 1,
            ],
        );
    }
}
