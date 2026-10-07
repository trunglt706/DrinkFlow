<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Campaign\ImportCampaignItemsAction;
use App\Enums\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CrawlerPreviewRequest;
use App\Http\Requests\ImportCampaignItemsRequest;
use App\Models\Campaign;
use App\Models\CrawlerPreview;
use App\Services\Audit\AuditService;
use App\Services\FoodCrawler\Exceptions\FoodCrawlerException;
use App\Services\FoodCrawler\FoodCrawlerGateway;
use Illuminate\Http\JsonResponse;

class CrawlerController extends Controller
{
    /**
     * Handle the preview operation.
     * @param CrawlerPreviewRequest $request Parameter value.
     * @param FoodCrawlerGateway $crawler Food crawler gateway.
     * @param AuditService $audit Activity audit service.
     * @return JsonResponse Result of the operation.
     */
    public function preview(CrawlerPreviewRequest $request, FoodCrawlerGateway $crawler, AuditService $audit): JsonResponse
    {
        try {
            $menu = $crawler->crawl($request->validated('url'));
        } catch (FoodCrawlerException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
        $items = $menu->items();
        $preview = CrawlerPreview::create([
            'room_id' => request()->attributes->get('room')->id,
            'admin_id' => request()->user('admin')->id,
            'source_url' => $request->validated('url'),
            'items' => $items,
            'expires_at' => now()->addMinutes(30),
        ]);
        $audit->record('campaign_item_import.previewed', 'crawler_preview', $preview->id, $preview->room_id, [], [
            'item_count' => count($items),
            'source_url' => $preview->source_url,
        ]);

        return response()->json([
            'data' => [
                'preview_id' => $preview->id,
                'source_url' => $preview->source_url,
                'provider' => $menu->provider,
                'restaurant' => ['external_id' => $menu->externalRestaurantId],
                'categories' => $menu->toArray()['categories'],
                'items' => $items,
                'expires_at' => $preview->expires_at,
            ],
        ], 201);
    }

    /**
     * Handle the import operation.
     * @param ImportCampaignItemsRequest $request Parameter value.
     * @param Campaign $campaign Parameter value.
     * @param ImportCampaignItemsAction $action Parameter value.
     * @param AuditService $audit Activity audit service.
     * @return JsonResponse Result of the operation.
     */
    public function import(ImportCampaignItemsRequest $request, Campaign $campaign, ImportCampaignItemsAction $action, AuditService $audit): JsonResponse
    {
        abort_unless($campaign->room_id === request()->attributes->get('room')->id, 404);
        if ($campaign->status === CampaignStatus::Closed) {
            abort(422, __('admin.campaign_closed'));
        }
        $items = $request->validated('items');
        if ($request->filled('preview_id')) {
            $preview = CrawlerPreview::query()->whereKey($request->integer('preview_id'))->where('room_id', $campaign->room_id)->where('admin_id', request()->user('admin')->id)->where('expires_at', '>', now())->firstOrFail();
            $sourceUrl = $preview->source_url;
        } else {
            $sourceUrl = $request->validated('source_url');
        }

        $importedItems = $action->execute($campaign, $sourceUrl, $items);
        $audit->record('campaign_item_import.completed', 'campaign', $campaign->id, $campaign->room_id, [], [
            'item_count' => count($importedItems),
            'source_url' => $sourceUrl,
            'item_ids' => collect($importedItems)->pluck('id')->all(),
        ]);

        return response()->json(['data' => $importedItems], 201);
    }
}
