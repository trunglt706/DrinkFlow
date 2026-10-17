<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Support\Helpers\MarkdownHelper;

class Version extends Model
{
    public const CACHE_KEY = 'app_latest_system_version';

    /** Key of the public versions menu (all releases, newest first) in the versions cache store. */
    public const MENU_CACHE_KEY = 'versions:menu';

    protected $fillable = [
        'version',
        'title',
        'changelog',
        'release_date',
        'force_refresh',
        'important',
        'created_by_admin_id',
        'created_by_superadmin_id',
    ];

    /**
     * Tự động ép kiểu các thuộc tính của model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'force_refresh' => 'boolean',
            'important' => 'boolean',
        ];
    }

    /**
     * Bootstrap model events để tự động làm mới cache khi tạo, cập nhật hoặc xóa version.
     */
    protected static function booted(): void
    {
        // Superadmin create / edit / delete all go through Eloquent, so these events keep both caches fresh.
        static::saved(function () {
            static::clearLatestVersionCache();
            static::clearMenuCache();
        });

        static::deleted(function () {
            static::clearLatestVersionCache();
            static::clearMenuCache();
        });
    }

    /**
     * Every release as plain rows, newest first, cached in the versions store (Redis by default).
     *
     * Only raw database values are cached; localization is applied by the caller, so one key serves every locale.
     * When the store is unreachable the rows are read straight from the database.
     *
     * @return list<array{version: string, title: ?string, changelog: ?string, release_date: ?string, important: bool, force_refresh: bool}>
     */
    public static function menuRows(): array
    {
        try {
            return static::menuCache()->rememberForever(self::MENU_CACHE_KEY, static fn (): array => static::queryMenuRows());
        } catch (\Throwable $exception) {
            Log::warning('Versions menu cache unavailable, reading the database.', ['message' => $exception->getMessage()]);

            return static::queryMenuRows();
        }
    }

    /**
     * Remove the cached versions menu so the next request rebuilds it from the database.
     */
    public static function clearMenuCache(): void
    {
        try {
            static::menuCache()->forget(self::MENU_CACHE_KEY);
        } catch (\Throwable $exception) {
            Log::warning('Could not clear the versions menu cache.', ['message' => $exception->getMessage()]);
        }
    }

    /**
     * Cache repository holding the versions menu.
     *
     * @return Repository Store configured by cache.versions_store.
     */
    private static function menuCache(): Repository
    {
        return Cache::store((string) config('cache.versions_store', 'redis'));
    }

    /**
     * Read every release from the database, newest first.
     *
     * @return list<array{version: string, title: ?string, changelog: ?string, release_date: ?string, important: bool, force_refresh: bool}>
     */
    private static function queryMenuRows(): array
    {
        return static::query()
            ->orderByDesc('release_date')
            ->orderByDesc('id')
            ->get(['version', 'title', 'changelog', 'release_date', 'important', 'force_refresh'])
            ->map(static fn (self $version): array => [
                'version' => (string) $version->version,
                'title' => $version->title,
                'changelog' => $version->changelog,
                'release_date' => $version->release_date?->toDateString(),
                'important' => (bool) $version->important,
                'force_refresh' => (bool) $version->force_refresh,
            ])
            ->all();
    }

    /**
     * Lấy chuỗi phiên bản mới nhất từ database kèm cache.
     *
     * @return string  Phiên bản hệ thống (ví dụ: 'v2.3.0')
     */
    public static function getLatestVersionString(): string
    {
        return (string) Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                if (!\Illuminate\Support\Facades\Schema::hasTable('versions')) {
                    return (string) config('app.version', 'v2.3.0');
                }

                $latest = static::query()
                    ->orderByDesc('release_date')
                    ->orderByDesc('id')
                    ->value('version');

                return $latest ?: (string) config('app.version', 'v2.3.0');
            } catch (\Throwable) {
                return (string) config('app.version', 'v2.3.0');
            }
        });
    }

    /**
     * Chuyển Markdown của changelog sang HTML an toàn để hiển thị.
     *
     * HTML thô trong nội dung bị escape và các liên kết không an toàn (javascript:, data:, …) bị loại bỏ.
     *
     * @param string $markdown Nội dung Markdown.
     * @return string HTML đã render.
     */
    public static function renderMarkdown(string $markdown): string
    {
        return MarkdownHelper::toSafeHtml($markdown);
    }

    /**
     * Xóa cache phiên bản hệ thống để làm mới ngay lập tức.
     */
    public static function clearLatestVersionCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
