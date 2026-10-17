<?php

declare(strict_types=1);

namespace App\View\Components\Room;

use App\Models\Room;
use App\Services\Birthday\RoomBirthdayService;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Birthday banner at the top of every room page: members whose birthday is within 3 days, each linking to their
 * celebration page.
 */
class BirthdayBanner extends Component
{
    /** @var list<array{user_code: string, name: string, avatar_url: string, day: int, month: int, offset: int}> */
    public array $celebrants;

    /**
     * @param Room $room Room whose members are checked.
     * @param string|null $viewerCode Member code of the viewer, to greet them personally on their own birthday.
     */
    public function __construct(public Room $room, RoomBirthdayService $birthdays, public ?string $viewerCode = null)
    {
        $this->celebrants = $birthdays->celebrants($room);
    }

    public function shouldRender(): bool
    {
        return $this->celebrants !== [];
    }

    public function render(): View
    {
        return view('components.room.birthday-banner');
    }
}
