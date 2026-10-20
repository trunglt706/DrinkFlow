<?php

declare(strict_types=1);

namespace App\View\Components\Room;

use App\Models\Room;
use App\Services\Occasion\RoomEventService;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Events banner at the top of every room page: a horizontal slider with one slide per occasion being celebrated
 * (on the day, Tết 7 days before and after) and one slide with the members whose birthday is within 3 days, each
 * linking to its page.
 */
class EventsBanner extends Component
{
    /** @var list<array{type: string, occasion?: \App\Enums\RoomOccasion, offset: int, celebrant?: array<string, mixed>}> */
    public array $events;

    /**
     * @param Room $room Room whose events are shown.
     * @param RoomEventService $roomEvents Room events.
     * @param string|null $viewerCode Member code of the viewer, to greet them personally on their own birthday.
     */
    public function __construct(public Room $room, RoomEventService $roomEvents, public ?string $viewerCode = null)
    {
        $this->events = $roomEvents->bannerEvents($room);
    }

    public function shouldRender(): bool
    {
        return $this->events !== [];
    }

    public function render(): View
    {
        return view('components.room.events-banner');
    }
}
