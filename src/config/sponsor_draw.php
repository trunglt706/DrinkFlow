<?php

// Sponsor draw sessions (App\Services\SponsorDraw): campaign landing page where admins draw the next sponsors.
return [
    // Minutes an access link stays valid. When it expires nobody (admins included) can open it or draw;
    // the session and its results stay available in the admin area and a new link can be issued.
    'link_ttl_minutes' => (int) env('SPONSOR_DRAW_LINK_TTL_MINUTES', 60),
    // Upper bound of winners per session (validation of the admin form).
    'max_winners' => (int) env('SPONSOR_DRAW_MAX_WINNERS', 50),
];
