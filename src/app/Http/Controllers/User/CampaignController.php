<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Actions\Campaign\DeclineCampaignAction;
use App\Actions\Campaign\RejoinCampaignAction;
use App\Enums\CampaignStatus;
use App\Enums\OrderStatus;
use App\Enums\GlobalUserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCampaignCartRequest;
use App\Http\Requests\UpdateCartProxyRequest;
use App\Http\Resources\CampaignResource;
use App\Models\Campaign;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Room;
use App\Models\RoomUser;
use App\Services\Order\ProxyOrderPolicy;
use App\Enums\RoomUserStatus;
use App\Services\Campaign\UserRoomCampaignService;
use App\Services\Debt\DebtCreditService;
use App\Support\Helpers\FormatHelper;
use App\Support\Privacy\PersonalDataMasker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class CampaignController extends Controller
{
    /**
     * Trả về top món được chọn nhiều nhất của một chiến dịch (nạp khi mở modal "Top món yêu thích").
     *
     * @param Request $request Current HTTP request (mang thông tin thành viên phòng).
     * @param Room $room Phòng hiện tại.
     * @param Campaign $campaign Chiến dịch cần thống kê.
     * @param UserRoomCampaignService $service Service thống kê món theo chiến dịch.
     * @return JsonResponse Payload dạng {"data": [{"rank": 1, "name": "...", "quantity": 3}]}.
     */
    public function favoriteItems(Request $request, Room $room, Campaign $campaign, UserRoomCampaignService $service): JsonResponse
    {
        abort_unless($campaign->room_id === $room->id, 404);

        /** @var RoomUser $roomUser */
        $roomUser = $request->attributes->get('room_user');

        return response()->json(['data' => $service->getFavoriteItems($campaign, (int) $roomUser->global_user_id)]);
    }

    /**
     * Get campaign details and its orders for modal display.
     * If campaign has full sponsor: returns all orders in the campaign.
     * If campaign has no sponsor or partial: returns only the current user's orders.
     *
     * @param Request $request Current HTTP request.
     * @param Room $room Current room.
     * @param Campaign $campaign Target campaign.
     * @return JsonResponse Campaign and orders detail payload.
     */
    public function details(Request $request, Room $room, Campaign $campaign): JsonResponse
    {
        abort_unless($campaign->room_id === $room->id, 404);

        /** @var RoomUser|null $roomUser */
        $roomUser = $request->attributes->get('room_user');
        $isFullSponsor = $campaign->sponsor_type === Campaign::SPONSOR_TYPE_FULL;

        $ordersQuery = $campaign->orders()
            ->with([
                'roomUser.globalUser',
                'items.toppings',
                'items.campaignItem',
            ])
            ->where('status', '!=', OrderStatus::Cancelled->value);

        if (! $isFullSponsor && $roomUser) {
            $ordersQuery->where('room_user_id', $roomUser->id);
        }

        $orders = $ordersQuery->oldest('id')->get();

        $orderData = $orders->map(function (Order $order): array {
            $roomUser = $order->roomUser;
            $globalUser = $roomUser?->globalUser;
            $displayName = $roomUser?->display_name ?: ($globalUser?->name ?: __('room.debts.campaign_orderer'));
            $userCode = $roomUser?->user_code ?: '';
            $avatarUrl = $globalUser?->avatar_url ?: null;
            $email = PersonalDataMasker::email($globalUser?->email);

            $items = $order->items->map(function (OrderItem $item): array {
                $toppings = $item->toppings->map(fn ($t) => [
                    'name' => $t->topping_name,
                    'price' => (int) ($t->unit_price ?: $t->subtotal),
                ])->values()->all();

                return [
                    'id' => $item->id,
                    'item_name' => $item->item_name,
                    'image_url' => $item->campaignItem?->image_url,
                    'size_name' => $item->size_name,
                    'unit_price' => (int) $item->unit_price,
                    'quantity' => (int) $item->quantity,
                    'ice_percent' => $item->ice_percent,
                    'sugar_percent' => $item->sugar_percent,
                    'line_subtotal' => (int) $item->line_subtotal,
                    'note' => $item->note,
                    'is_self_paid' => (bool) $item->is_self_paid,
                    'toppings' => $toppings,
                ];
            })->values()->all();

            return [
                'id' => $order->id,
                'code' => $order->code,
                'orderer_name' => $displayName,
                'orderer_code' => $userCode,
                'orderer_avatar' => $avatarUrl,
                'orderer_email' => $email,
                'status' => $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status,
                'status_label' => $order->status instanceof OrderStatus ? $order->status->label() : (string) $order->status,
                'subtotal' => (int) $order->subtotal,
                'delivery_amount' => (int) $order->delivery_amount,
                'discount_amount' => (int) $order->discount_amount,
                'sponsor_amount' => (int) $order->sponsor_amount,
                'final_amount' => (int) $order->final_amount,
                'note' => $order->note,
                'items' => $items,
            ];
        })->values()->all();

        return response()->json([
            'data' => [
                'id' => $campaign->id,
                'code' => $campaign->code,
                'name' => $campaign->name,
                'restaurant' => $campaign->restaurant,
                'status' => $campaign->status instanceof CampaignStatus ? $campaign->status->value : (string) $campaign->status,
                'sponsor_type' => $campaign->sponsor_type,
                'sponsor_name' => $campaign->sponsor_name,
                'sponsor_description' => $campaign->sponsor_description,
                'is_full_sponsor' => $isFullSponsor,
                'delivery_fee' => (int) ($campaign->delivery_fee ?? 0),
                'discount' => (int) ($campaign->discount ?? 0),
                'total_orders' => count($orderData),
                'total_cups' => $orders->sum(fn ($o) => $o->items->sum('quantity')),
                'orders' => $orderData,
            ],
        ]);
    }
    /**
     * Add a configured campaign item to the current user's session cart.
     *
     * @param Request $request Current HTTP request.
     * @param \App\Models\Room $room Current room.
     * @param Campaign $campaign Target campaign.
     * @return JsonResponse Session cart payload.
     */
    public function addToCart(StoreCampaignCartRequest $request, \App\Models\Room $room, Campaign $campaign): JsonResponse
    {
        abort_unless($campaign->room_id === $room->id, 404);

        if (! $campaign->isOrderable()) {
            throw ValidationException::withMessages([
                'campaign' => $campaign->orderingClosedMessage(),
            ]);
        }

        /** @var \App\Models\RoomUser|null $roomUser */
        $roomUser = $request->attributes->get('room_user');
        if ($roomUser) {
            $hasDeclined = \App\Models\CampaignParticipant::query()
                ->where('campaign_id', $campaign->id)
                ->where('room_user_id', $roomUser->id)
                ->where('status', \App\Models\CampaignParticipant::STATUS_DECLINED)
                ->exists();
            if ($hasDeclined) {
                throw ValidationException::withMessages([
                    'campaign' => __('room.campaign.declined'),
                ]);
            }
        }

        $data = $request->validated();

        $item = $campaign->items()->with(['sizes', 'toppings'])->whereKey($data['item_id'])->where('status', 'active')->firstOrFail();
        $size = ! empty($data['size_id']) ? $item->sizes->firstWhere('id', (int) $data['size_id']) : null;
        abort_if(! empty($data['size_id']) && ! $size, 422, __('admin.invalid_size'));
        $toppings = collect($data['topping_ids'] ?? [])->map(fn (int $id) => $item->toppings->firstWhere('id', $id))->filter();
        abort_if($toppings->count() !== count($data['topping_ids'] ?? []), 422, __('admin.invalid_topping'));

        if ($roomUser instanceof RoomUser) {
            app(ProxyOrderPolicy::class)->assertNotSelf($roomUser, $data['proxy_user_code'] ?? null, 'proxy_user_code');
        }

        $isSelfPaid = filter_var($data['is_self_paid'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $proxyUserCode = isset($data['proxy_user_code']) && $data['proxy_user_code'] !== '' ? (string) $data['proxy_user_code'] : null;
        if ($isSelfPaid && $proxyUserCode !== null) {
            throw ValidationException::withMessages([
                'proxy_user_code' => __('room.campaign.proxy_self_paid_not_allowed'),
            ]);
        }

        $cartKey = $this->cartKey($room->id, $campaign->id);
        $cart = session()->get($cartKey, []);
        abort_if(count($cart) >= 50, 422, __('room.campaign.cart_limit_reached'));
        $cart[] = [
            'item_id'         => (int) $item->id,
            'item_name'       => $item->name,
            'image_url'       => $item->image_url,
            'size_id'         => $size?->id,
            'size_name'       => $size?->name,
            'topping_ids'     => $toppings->pluck('id')->values()->all(),
            'topping_names'   => $toppings->pluck('name')->values()->all(),
            'quantity'        => (int) $data['quantity'],
            'note'            => $data['note'] ?? null,
            'unit_price'      => (int) $item->base_price + (int) ($size?->price_delta ?? 0) + (int) $toppings->sum('price'),
            'is_self_paid'    => $isSelfPaid,
            'proxy_user_code' => $proxyUserCode,
        ];
        session()->put($cartKey, $cart);

        return response()->json(['data' => $cart]);
    }

    /**
     * Build the session key used by one room campaign cart.
     *
     * @param int $roomId Room identifier.
     * @param int $campaignId Campaign identifier.
     * @return string Session key.
     */
    private function cartKey(int $roomId, int $campaignId): string
    {
        return "room_campaign_cart_{$roomId}_{$campaignId}";
    }

    /**
     * Remove one item from the current room campaign session cart.
     *
     * @param Request $request Current HTTP request.
     * @param \App\Models\Room $room Current room.
     * @param Campaign $campaign Target campaign.
     * @param int $index Zero-based cart item index.
     * @return JsonResponse Updated cart payload.
     */
    public function removeFromCart(Request $request, \App\Models\Room $room, Campaign $campaign, int $index): JsonResponse
    {
        abort_unless($campaign->room_id === $room->id, 404);
        $cartKey = $this->cartKey($room->id, $campaign->id);
        $cart = session()->get($cartKey, []);
        abort_if(! array_key_exists($index, $cart), 404);
        unset($cart[$index]);
        $cart = array_values($cart);
        session()->put($cartKey, $cart);

        return response()->json(['data' => $cart]);
    }

    /**
     * Update the proxy recipient attached to one cart item.
     *
     * @param Request $request Incoming request.
     * @param Room $room Current room.
     * @param Campaign $campaign Target campaign.
     * @param int $index Zero-based cart item index.
     * @return JsonResponse Updated cart payload.
     */
    /**
     * Update cart item proxy information (person ordering for someone else).
     *
     * @param UpdateCartProxyRequest $request Incoming request with validated data.
     * @param Room $room Current room.
     * @param Campaign $campaign Target campaign.
     * @param int $index Zero-based cart item index.
     * @return JsonResponse Updated cart payload.
     */
    public function updateCartProxy(UpdateCartProxyRequest $request, Room $room, Campaign $campaign, int $index): JsonResponse
    {
        abort_unless($campaign->room_id === $room->id, 404);
        $data = $request->validated();
        $cartKey = $this->cartKey($room->id, $campaign->id);
        $cart = session()->get($cartKey, []);
        abort_if(! array_key_exists($index, $cart), 404);
        $proxyCode = trim((string) ($data['proxy_user_code'] ?? '')) ?: null;
        if ($proxyCode !== null && ! empty($cart[$index]['is_self_paid'])) {
            throw ValidationException::withMessages([
                'proxy_user_code' => __('room.campaign.proxy_self_paid_not_allowed'),
            ]);
        }
        $policy = app(ProxyOrderPolicy::class);
        $requester = $request->attributes->get('room_user');
        if ($requester instanceof RoomUser) {
            $policy->assertNotSelf($requester, $proxyCode, 'proxy_user_code');
        }
        $cart[$index]['proxy_user_code'] = $proxyCode;
        $cart[$index]['proxy_user_name'] = trim((string) ($data['proxy_user_name'] ?? '')) ?: null;
        // Assigning items to others must leave at least one item for the requester.
        $policy->assertHasOwnItems(array_values($cart), 'proxy_user_code');
        session()->put($cartKey, array_values($cart));

        return response()->json(['data' => array_values($cart)]);
    }

    /**
     * Look up an active room member by code, email, or phone number.
     *
     * @param Request $request Incoming request.
     * @param Room $room Current room.
     * @return JsonResponse Member summary.
     */
    public function lookupMember(Request $request, Room $room): JsonResponse
    {
        $search = trim((string) $request->query('q', $request->query('code', '')));
        abort_if($search === '' || mb_strlen($search) > 255, 404);
        $normalizedSearch = Str::lower($search);
        $normalizedPhone = preg_replace('/[^0-9+]/', '', $search) ?: '';
        $roomUser = RoomUser::query()
            ->with('globalUser')
            ->where('room_id', $room->id)
            ->where('status', RoomUserStatus::Active->value)
            ->where(static function ($query) use ($normalizedSearch): void {
                $query->whereRaw('LOWER(user_code) = ?', [$normalizedSearch])
                    ->orWhereHas('globalUser', static function ($gq) use ($normalizedSearch): void {
                        $gq->where('status', GlobalUserStatus::Active->value)
                            ->whereRaw('LOWER(email) = ?', [$normalizedSearch]);
                    });
            })
            ->whereHas('globalUser', static fn ($query) => $query->where('status', GlobalUserStatus::Active->value))
            ->first();

        if ($roomUser === null && $normalizedPhone !== '') {
            $candidates = RoomUser::query()
                ->with('globalUser')
                ->where('room_id', $room->id)
                ->where('status', RoomUserStatus::Active->value)
                ->whereHas('globalUser', static fn ($query) => $query->where('status', GlobalUserStatus::Active->value))
                ->get();

            $roomUser = $candidates->first(static function (RoomUser $candidate) use ($normalizedPhone): bool {
                $phone = preg_replace('/[^0-9+]/', '', (string) ($candidate->globalUser?->phone ?? '')) ?: '';

                return $phone !== '' && $phone === $normalizedPhone;
            });
        }

        abort_if($roomUser === null, 404);
        // Ordering on behalf of yourself is not allowed: the requester is excluded from the lookup.
        $requester = $request->attributes->get('room_user');
        abort_if($requester instanceof RoomUser && $roomUser->id === $requester->id, 422, __('room.campaign.proxy_self_not_allowed'));

        $credit = app(DebtCreditService::class);
        $creditPolicy = $credit->policy($room);
        abort_if($creditPolicy['enabled'] && $credit->used($roomUser) >= $creditPolicy['ceiling'], 422, __('room.campaign.proxy_debt_limit_reached', ['limit' => FormatHelper::formatCurrency($creditPolicy['ceiling'])]));

        return response()->json([
            'display_name' => $roomUser->display_name ?: $roomUser->globalUser?->name,
            'email' => PersonalDataMasker::email($roomUser->globalUser?->email),
            'phone' => PersonalDataMasker::phone($roomUser->globalUser?->phone),
            'user_code' => $roomUser->user_code,
            'avatar_url' => $roomUser->globalUser?->avatar_url,
        ]);
    }

    /**
     * Clear the current room campaign session cart.
     *
     * @param Request $request Current HTTP request.
     * @param \App\Models\Room $room Current room.
     * @param Campaign $campaign Target campaign.
     * @return JsonResponse Empty cart payload.
     */
    public function clearCart(Request $request, \App\Models\Room $room, Campaign $campaign): JsonResponse
    {
        abort_unless($campaign->room_id === $room->id, 404);
        session()->forget($this->cartKey($room->id, $campaign->id));

        return response()->json(['data' => []]);
    }

    /**
     * Hiển thị danh sách chiến dịch hoặc menu món đang diễn ra trong phòng.
     *
     * @param  \Illuminate\Http\Request  $request  Đối tượng HTTP Request
     * @param  \App\Services\Campaign\UserRoomCampaignService  $service  Service xử lý chiến dịch phòng
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Contracts\View\View  Phản hồi JSON hoặc Giao diện View
     */
    public function index(Request $request, UserRoomCampaignService $service): JsonResponse|View
    {
        $room = $request->attributes->get('room');
        $roomUser = $request->attributes->get('room_user');
        $user = $request->attributes->get('global_user') ?? $request->user('web');

        if ($request->expectsJson() || $request->filled('q')) {
            $campaigns = $service->searchCampaigns($room, $request);
            return response()->json(['data' => $campaigns]);
        }

        $data = $service->getCampaignViewData($room, $roomUser, $user);

        return view('user.campaign', $data);
    }

    /**
     * Xem thông tin chi tiết một chiến dịch cụ thể trong phòng.
     *
     * @param  \App\Models\Room  $room  Phòng chứa chiến dịch
     * @param  \App\Models\Campaign  $campaign  Chiến dịch cần xem
     * @param  \Illuminate\Http\Request  $request  Đối tượng HTTP Request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    public function show(Room $room, Campaign $campaign, Request $request): JsonResponse|View|RedirectResponse
    {
        abort_unless($campaign->room_id === $room->id && in_array($campaign->status?->value, ['active', 'scheduled'], true), 404);

        if ($request->expectsJson()) {
            $campaign->load(['items' => fn ($q) => $q->where('status', 'active')->with(['sizes', 'toppings']), 'room']);

            return response()->json(['data' => (new CampaignResource($campaign))->resolve($request)]);
        }

        return redirect()->route('user.campaigns.index', $room->slug);
    }

    /**
     * Record that the authenticated room member will not participate in a campaign.
     *
     * @param Request $request Current HTTP request.
     * @param Room $room Current room.
     * @param Campaign $campaign Campaign being declined.
     * @param DeclineCampaignAction $action Participation action.
     * @return JsonResponse Declined participation payload.
     */
    public function decline(Request $request, \App\Models\Room $room, Campaign $campaign, DeclineCampaignAction $action): JsonResponse
    {
        /** @var \App\Models\RoomUser $roomUser */
        $roomUser = $request->attributes->get('room_user');
        abort_unless($campaign->room_id === $room->id, 404);
        $this->ensureCampaignIsOrderable($campaign);

        session()->forget($this->cartKey($room->id, $campaign->id));

        return response()->json(['data' => $action->execute($campaign, $roomUser)]);
    }

    /**
     * Restore the authenticated room member's participation in an active campaign.
     *
     * @param Request $request Current HTTP request.
     * @param \App\Models\Room $room Current room.
     * @param Campaign $campaign Campaign being rejoined.
     * @param RejoinCampaignAction $action Participation action.
     * @return JsonResponse Rejoin response.
     */
    public function rejoin(Request $request, \App\Models\Room $room, Campaign $campaign, RejoinCampaignAction $action): JsonResponse
    {
        /** @var \App\Models\RoomUser $roomUser */
        $roomUser = $request->attributes->get('room_user');
        abort_unless($campaign->room_id === $room->id, 404);
        $this->ensureCampaignIsOrderable($campaign);

        $action->execute($campaign, $roomUser);

        return response()->json(['data' => ['status' => 'pending']]);
    }

    /**
     * Ensure that campaign participation changes are only available while ordering is open.
     *
     * @param Campaign $campaign Campaign being updated.
     * @return void
     * @throws ValidationException When the campaign is not currently orderable.
     */
    private function ensureCampaignIsOrderable(Campaign $campaign): void
    {
        if (! $campaign->isOrderable()) {
            throw ValidationException::withMessages([
                'campaign' => $campaign->orderingClosedMessage(),
            ]);
        }
    }
}
