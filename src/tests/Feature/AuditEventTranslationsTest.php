<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Support\Facades\App;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Every audit event recorded with a literal key must have a translated label in vi, en and ja, otherwise the audit
 * page shows the raw key (e.g. "sponsor_draw.configured").
 */
class AuditEventTranslationsTest extends TestCase
{
    public function test_every_recorded_audit_event_has_a_label_in_every_locale(): void
    {
        $events = [];
        foreach (Finder::create()->files()->in(app_path())->name('*.php') as $file) {
            preg_match_all("/->record\(\s*'([a-z_]+\.[a-z_]+)'/", $file->getContents(), $matches);
            preg_match_all("/\? '([a-z_]+\.[a-z_]+)' : '([a-z_]+\.[a-z_]+)'/", $file->getContents(), $ternaries);
            array_push($events, ...$matches[1], ...$ternaries[1], ...$ternaries[2]);
        }
        // Built dynamically in SponsorDrawService::refreezeSnapshot().
        array_push($events, 'sponsor_draw.eligible_removed', 'sponsor_draw.eligible_synced');
        $events = array_values(array_unique($events));
        $this->assertNotEmpty($events);

        $missing = [];
        foreach (['vi', 'en', 'ja'] as $locale) {
            App::setLocale($locale);
            foreach ($events as $event) {
                if (AuditLog::labelForEvent($event) === $event) {
                    $missing[] = $locale.': '.$event;
                }
            }
        }

        $this->assertSame([], $missing, 'Audit events without a translated label.');
    }
}
