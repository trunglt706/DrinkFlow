<?php

declare(strict_types=1);

namespace App\Services\Guide;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use League\CommonMark\GithubFlavoredMarkdownConverter;

/**
 * Đọc và render các bộ bài hướng dẫn Markdown trong public/guide-content.
 *
 * Có hai bộ tài liệu độc lập, phân biệt bằng "audience":
 *  - client: hướng dẫn cho thành viên đặt món (/guides, /me/guides, /rooms/{room}/guides);
 *  - agent: hướng dẫn cho Đại lý quản trị phòng ban (/guides/agent).
 *
 * Mỗi bộ nằm trong một thư mục riêng và giữ nguyên quy ước đặt tên "NN-slug.md" của dự án, nên cả hai
 * dùng chung toàn bộ logic đọc file, render và viết lại liên kết nội bộ; chỉ khác thư mục gốc.
 */
class UserGuideService
{
    /** Bộ hướng dẫn dành cho thành viên đặt món (Client). */
    public const AUDIENCE_CLIENT = 'client';

    /** Bộ hướng dẫn dành cho Đại lý quản trị phòng ban (Agent). */
    public const AUDIENCE_AGENT = 'agent';

    /** Bộ hướng dẫn nội bộ dành cho Superadmin (chỉ xem sau khi đăng nhập trang superadmin). */
    public const AUDIENCE_SUPERADMIN = 'superadmin';

    /**
     * Thư mục (tương đối trong public/) của từng bộ hướng dẫn công khai.
     *
     * @var array<string, string>
     */
    private const DIRECTORIES = [
        self::AUDIENCE_CLIENT => 'guide-content/user',
        self::AUDIENCE_AGENT => 'guide-content/admin',
    ];

    /**
     * Thư mục (tương đối trong resources/) của các bộ hướng dẫn nội bộ: không nằm trong public/ nên
     * không thể tải trực tiếp qua URL tĩnh, chỉ được render qua route có xác thực.
     *
     * @var array<string, string>
     */
    private const PRIVATE_DIRECTORIES = [
        self::AUDIENCE_SUPERADMIN => 'guide-content/superadmin',
    ];

    /**
     * Slug không được xem là một bài hướng dẫn thật sự (file mục lục nội bộ).
     */
    private const INDEX_SLUG = 'README';

    /**
     * Mọi bộ hướng dẫn được phục vụ.
     *
     * @return array<int, string> Danh sách audience.
     */
    public static function audiences(): array
    {
        return array_keys(self::DIRECTORIES);
    }

    /**
     * Lấy danh sách bài hướng dẫn (đã sắp xếp theo số thứ tự trong tên file) để hiển thị dạng lưới.
     *
     * @param string $audience Bộ hướng dẫn (client hoặc agent).
     * @return array<int, array{slug: string, number: int, title: string, excerpt: string}>
     */
    public function list(string $audience = self::AUDIENCE_CLIENT): array
    {
        $directory = $this->directory($audience);

        if (! File::isDirectory($directory)) {
            return [];
        }

        $articles = [];

        foreach (File::files($directory) as $file) {
            if (strtolower($file->getExtension()) !== 'md') {
                continue;
            }

            $slug = $file->getFilenameWithoutExtension();
            if (strcasecmp($slug, self::INDEX_SLUG) === 0) {
                continue;
            }

            $raw = File::get($file->getPathname());

            $articles[] = [
                'slug' => $slug,
                'number' => $this->extractNumber($slug),
                'title' => $this->extractTitle($raw) ?: $slug,
                'excerpt' => $this->extractExcerpt($raw),
            ];
        }

        usort($articles, static fn (array $a, array $b): int => $a['number'] <=> $b['number'] ?: $a['slug'] <=> $b['slug']);

        return $articles;
    }

    /**
     * Đọc một bài hướng dẫn cho trang công khai, kèm đoạn mô tả ngắn dùng cho thẻ SEO.
     *
     * @param string $slug Định danh bài viết lấy từ URL.
     * @param string $indexUrl URL trang danh sách công khai (gốc cho các liên kết nội bộ).
     * @param string $audience Bộ hướng dẫn (client hoặc agent).
     * @return array{slug: string, title: string, html: string, excerpt: string}|null Dữ liệu bài viết hoặc null nếu không tìm thấy.
     */
    public function findPublic(string $slug, string $indexUrl, string $audience = self::AUDIENCE_CLIENT): ?array
    {
        $article = $this->find($slug, $indexUrl, $audience);
        if ($article === null) {
            return null;
        }

        $raw = File::get($this->directory($audience) . DIRECTORY_SEPARATOR . $slug . '.md');

        return $article + ['excerpt' => $this->extractExcerpt($raw)];
    }

    /**
     * Đọc và render nội dung một bài hướng dẫn thành HTML an toàn để hiển thị.
     *
     * Đường dẫn trang chi tiết của mỗi bài luôn có dạng "{$indexUrl}/{slug}" (route index + route show
     * dùng chung 1 prefix), nên chỉ cần truyền URL trang danh sách là đủ để viết lại mọi liên kết nội bộ.
     *
     * @param string $slug Định danh file (tên file không có phần mở rộng .md), lấy từ URL.
     * @param string $indexUrl URL của trang danh sách hướng dẫn (route index, dùng làm gốc cho link chi tiết và link README.md).
     * @param string $audience Bộ hướng dẫn (client hoặc agent).
     * @return array{slug: string, title: string, html: string}|null Dữ liệu bài viết, hoặc null nếu không tìm thấy/slug không hợp lệ.
     */
    public function find(string $slug, string $indexUrl, string $audience = self::AUDIENCE_CLIENT): ?array
    {
        if (! preg_match('/^[a-z0-9\-]+$/', $slug) || strcasecmp($slug, self::INDEX_SLUG) === 0) {
            return null;
        }

        $directory = $this->directory($audience);
        $path = realpath($directory . DIRECTORY_SEPARATOR . $slug . '.md');
        $baseDirReal = realpath($directory);

        if ($path === false || $baseDirReal === false || ! Str::startsWith($path, $baseDirReal)) {
            return null;
        }

        $raw = File::get($path);

        return [
            'slug' => $slug,
            'title' => $this->extractTitle($raw) ?: $slug,
            'html' => $this->renderHtml($raw, $indexUrl, $audience),
        ];
    }

    /**
     * Đường dẫn tuyệt đối tới thư mục chứa Markdown của một bộ hướng dẫn.
     *
     * @param string $audience Bộ hướng dẫn.
     * @return string Đường dẫn thư mục; audience lạ được quy về bộ Client.
     */
    public function directory(string $audience): string
    {
        if (isset(self::PRIVATE_DIRECTORIES[$audience])) {
            return resource_path(self::PRIVATE_DIRECTORIES[$audience]);
        }

        return public_path(self::DIRECTORIES[$audience] ?? self::DIRECTORIES[self::AUDIENCE_CLIENT]);
    }

    /**
     * Trích số thứ tự đứng đầu tên file (ví dụ "12-dat-ho..." -> 12) để sắp xếp danh sách.
     *
     * @param string $slug Tên file không có phần mở rộng.
     * @return int Số thứ tự, hoặc PHP_INT_MAX nếu tên file không có số thứ tự.
     */
    private function extractNumber(string $slug): int
    {
        return preg_match('/^(\d+)-/', $slug, $m) ? (int) $m[1] : PHP_INT_MAX;
    }

    /**
     * Lấy tiêu đề bài viết từ dòng heading H1 đầu tiên trong Markdown.
     *
     * @param string $raw Nội dung Markdown gốc.
     * @return string Tiêu đề (rỗng nếu không tìm thấy).
     */
    private function extractTitle(string $raw): string
    {
        return preg_match('/^#\s+(.+)$/m', $raw, $m) ? trim($m[1]) : '';
    }

    /**
     * Trích đoạn mô tả ngắn (đoạn văn bản thuần đầu tiên, bỏ qua heading/nav/ảnh/list) để hiển thị trên thẻ danh sách.
     *
     * @param string $raw Nội dung Markdown gốc.
     * @return string Đoạn mô tả ngắn đã loại bỏ cú pháp Markdown, giới hạn độ dài.
     */
    private function extractExcerpt(string $raw): string
    {
        $withoutNav = $this->stripNavBlock($raw);
        $lines = preg_split('/\r?\n/', $withoutNav) ?: [];

        $paragraph = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                if ($paragraph !== []) {
                    break;
                }
                continue;
            }

            if (preg_match('/^(#{1,6}\s|!\[|<|>|-\s|\d+\.\s|\|)/', $trimmed)) {
                continue;
            }

            $paragraph[] = $trimmed;
        }

        $text = $this->stripInlineMarkdown(implode(' ', $paragraph));

        return Str::limit($text, 160);
    }

    /**
     * Loại bỏ khối điều hướng "<details>...</details>" ở đầu file (chỉ dùng để đọc trên GitHub, không cần hiển thị lại).
     *
     * @param string $raw Nội dung Markdown gốc.
     * @return string Nội dung sau khi loại bỏ khối nav.
     */
    private function stripNavBlock(string $raw): string
    {
        return preg_replace('/<details[^>]*>.*?<\/details>\s*/is', '', $raw, 1) ?? $raw;
    }

    /**
     * Loại bỏ cú pháp Markdown cơ bản (đậm/nghiêng/link/code) khỏi một đoạn văn bản, chỉ giữ lại nội dung hiển thị.
     *
     * @param string $text Đoạn văn bản có thể chứa cú pháp Markdown.
     * @return string Văn bản thuần.
     */
    private function stripInlineMarkdown(string $text): string
    {
        $text = preg_replace('/!\[[^\]]*\]\([^)]*\)/', '', $text) ?? $text;
        $text = preg_replace('/\[([^\]]+)\]\([^)]*\)/', '$1', $text) ?? $text;
        $text = preg_replace('/[*_`]{1,3}/', '', $text) ?? $text;

        return trim($text);
    }

    /**
     * Render Markdown thành HTML: loại nav nội bộ, viết lại đường dẫn ảnh/liên kết nội bộ, rồi convert qua CommonMark (GFM).
     *
     * @param string $raw Nội dung Markdown gốc.
     * @param string $indexUrl URL trang danh sách (gốc cho liên kết README.md và liên kết sang bài khác).
     * @param string $audience Bộ hướng dẫn (quyết định thư mục ảnh).
     * @return string HTML đã render, an toàn để hiển thị trực tiếp.
     */
    private function renderHtml(string $raw, string $indexUrl, string $audience): string
    {
        $content = $this->stripNavBlock($raw);

        // Ảnh tương đối "images/xxx.png" -> đường dẫn tuyệt đối tới thư mục images của bộ hướng dẫn.
        $imagesBase = '/'.(self::DIRECTORIES[$audience] ?? self::DIRECTORIES[self::AUDIENCE_CLIENT]).'/images/';
        $content = str_replace('](images/', ']('.$imagesBase, $content);

        // Liên kết nội bộ tới các bài khác ("12-slug.md" hoặc "12-slug.md#anchor") -> route trang chi tiết tương ứng.
        $content = preg_replace_callback(
            '/\]\((?:\.\/)?([a-z0-9\-]+)\.md(#[^)\s]*)?\)/i',
            function (array $m) use ($indexUrl): string {
                if (strcasecmp($m[1], self::INDEX_SLUG) === 0) {
                    return ']('.$indexUrl.')';
                }

                return ']('.rtrim($indexUrl, '/').'/'.$m[1].($m[2] ?? '').')';
            },
            $content
        ) ?? $content;

        $converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        $html = (string) $converter->convert($content);

        // Bổ sung loading="lazy" cho tất cả thẻ <img> trong bài viết hướng dẫn
        return preg_replace('/<img\b(?![^>]*\bloading=)/i', '<img loading="lazy"', $html) ?? $html;
    }
}
