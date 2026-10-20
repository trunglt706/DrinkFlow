<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Version;
use App\Services\Media\ImageUploadService;
use App\Support\Helpers\FormatHelper;
use Carbon\Carbon;
use Database\Seeders\VersionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageUploadAndVersionCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_upload_service_converts_image_to_webp_and_stores_in_public_disk(): void
    {
        Storage::fake('public');

        $service = app(ImageUploadService::class);
        $fakeFile = UploadedFile::fake()->image('profile_pic.png', 1000, 800);

        $url = $service->uploadAvatar($fakeFile);

        if (!function_exists('imagewebp')) {
            $this->markTestSkipped('GD does not support WebP on this environment.');
        }

        $this->assertNotEmpty($url);
        $this->assertStringEndsWith('.webp', $url);

        // Verify storage file
        $files = Storage::disk('public')->allFiles('uploads/avatars');
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.webp', $files[0]);

        $content = Storage::disk('public')->get($files[0]);
        $this->assertNotEmpty($content);
        // Check WebP signature ('RIFF' at 0 and 'WEBP' at 8)
        $this->assertSame('RIFF', substr($content, 0, 4));
        $this->assertSame('WEBP', substr($content, 8, 4));
    }

    public function test_version_seeder_seeds_default_versions_and_clears_cache(): void
    {
        $superadmin = $this->createSuperadmin([
            'name' => 'Super Admin',
            'email' => 'superadmin@drinkflow.local',
            'password' => Hash::make('secret'),
            'status' => 'active',
        ]);

        $this->seed(VersionSeeder::class);

        $this->assertDatabaseHas('versions', [
            'version' => 'v2.3.0',
            'title' => 'Multi-room Ordering, Proxy Orders & VietQR Settlement',
        ]);
        $this->assertDatabaseHas('versions', [
            'version' => 'v2.4.0',
            'title' => 'Room Events, Lucky Draw & Yearly Achievements',
        ]);

        $this->assertSame('v2.4.0', Version::getLatestVersionString());
    }

    public function test_version_model_latest_string_caches_and_invalidates_on_crud(): void
    {
        Cache::flush();

        Version::create([
            'version' => 'v2.3.0',
            'title' => 'Release v2.3.0',
            'release_date' => '2026-09-10',
        ]);

        $this->assertSame('v2.3.0', Version::getLatestVersionString());
        $this->assertTrue(Cache::has(Version::CACHE_KEY));

        // Create new latest version -> should automatically invalidate cache
        Version::create([
            'version' => 'v3.0.0',
            'title' => 'Release v3.0.0',
            'release_date' => '2026-10-01',
        ]);

        $this->assertSame('v3.0.0', Version::getLatestVersionString());

        // Update version -> should automatically invalidate cache
        $v3 = Version::where('version', 'v3.0.0')->first();
        $v3->delete();

        $this->assertSame('v2.3.0', Version::getLatestVersionString());
    }

    public function test_date_and_datetime_formatting_helpers_and_configs(): void
    {
        $this->assertSame('d/m/Y', config('app.date_format'));
        $this->assertSame('d/m/Y H:i:s', config('app.datetime_format'));

        $testDate = Carbon::create(2026, 9, 14, 15, 30, 45);

        $this->assertSame('14/09/2026', FormatHelper::formatDate($testDate));
        $this->assertSame('14/09/2026 15:30:45', FormatHelper::formatDateTime($testDate));

        // Carbon macros
        $this->assertSame('14/09/2026', $testDate->toAppDate());
        $this->assertSame('14/09/2026 15:30:45', $testDate->toAppDateTime());
    }

    public function test_public_pages_render_latest_version_from_database(): void
    {
        Version::create([
            'version' => 'v2.3.0',
            'title' => 'Release v2.3.0',
            'release_date' => '2026-09-10',
        ]);

        $landingRes = $this->get('/');
        $landingRes->assertStatus(200);
        $landingRes->assertSee('v2.3.0');

        $termsRes = $this->get('/terms');
        $termsRes->assertStatus(200);
        $termsRes->assertSee('v2.3.0');

        $contactRes = $this->get('/contact');
        $contactRes->assertStatus(200);
        $contactRes->assertSee('v2.3.0');

        $versionsRes = $this->get('/versions');
        $versionsRes->assertStatus(200);
        $versionsRes->assertSee('v2.3.0');
    }

    public function test_bank_service_loads_and_caches_banks_from_json(): void
    {
        $bankService = app(\App\Services\Common\BankService::class);
        $bankService->clearCache();

        $banks = $bankService->getAllBanks();
        $this->assertNotEmpty($banks);

        // Check common Vietnamese banks
        $vcb = $bankService->getBankByCode('VCB');
        $this->assertNotNull($vcb);
        $this->assertSame('Vietcombank', $vcb['short_name']);

        $mb = $bankService->getBankByCode('MB');
        $this->assertNotNull($mb);
        $this->assertSame('MBBank', $mb['short_name']);

        $options = $bankService->getBankSelectOptions();
        $this->assertArrayHasKey('VCB', $options);
        $this->assertArrayHasKey('MB', $options);
    }
}
