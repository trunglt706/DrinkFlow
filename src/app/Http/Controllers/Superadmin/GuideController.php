<?php

declare(strict_types=1);

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Services\Guide\UserGuideService;
use Illuminate\Contracts\View\View;

/**
 * Superadmin handbook (/superadmin/guides): the internal Markdown guides of the platform console.
 */
class GuideController extends Controller
{
    /**
     * List of the handbook articles.
     *
     * @param UserGuideService $guides Guide reader.
     * @return View Article list.
     */
    public function index(UserGuideService $guides): View
    {
        return view('superadmin.guides.index', [
            'articles' => array_map(
                static fn (array $article): array => $article + ['url' => route('superadmin.guides.show', $article['slug'])],
                $guides->list(UserGuideService::AUDIENCE_SUPERADMIN),
            ),
        ]);
    }

    /**
     * One handbook article rendered as HTML.
     *
     * @param string $slug Article slug (file name without .md).
     * @param UserGuideService $guides Guide reader.
     * @return View Article page.
     */
    public function show(string $slug, UserGuideService $guides): View
    {
        $article = $guides->find($slug, route('superadmin.guides.index'), UserGuideService::AUDIENCE_SUPERADMIN);
        abort_if($article === null, 404);

        $articles = $guides->list(UserGuideService::AUDIENCE_SUPERADMIN);
        $position = array_search($slug, array_column($articles, 'slug'), true);

        return view('superadmin.guides.show', [
            'article' => $article,
            'articles' => $articles,
            'previous' => $position !== false && $position > 0 ? $articles[$position - 1] : null,
            'next' => $position !== false && isset($articles[$position + 1]) ? $articles[$position + 1] : null,
        ]);
    }
}
