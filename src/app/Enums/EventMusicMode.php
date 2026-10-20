<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Background music of a room event page: the built-in melody (synthesised in the browser, public domain tunes),
 * an audio file link chosen by the room admin, or none.
 */
enum EventMusicMode: string
{
    case Builtin = 'builtin';
    case Custom = 'custom';
    case Off = 'off';
}
