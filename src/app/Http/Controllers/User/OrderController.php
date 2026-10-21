<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Actions\Order\CreateOrderAction;
use App\Actions\Order\CreateProxyOrdersAction;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListMemberOrdersRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Campaign;
use App\Models\Debt;
use App\Models\Order;
use App\Models\Room;
use App\Models\RoomUser;
use App\Services\Order\MemberOrderPageService;
use App\Services\Payment\ReceivingAccountResolver;
use App\Services\Payment\VietQrService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    /**
     * "My orders": the member's order in the room's live campaign at the top (as before), and below it the history
     * of their earlier orders with filters and pagination — shown even when no campaign is open. JSON requests get
     * the live campaign orders.
     *
     * @param ListMemberOrdersRequest $request Current request with the history filters.
     * @param MemberOrderPageService $orderPage Builds the shared list/order-page view data.
     * @return JsonResponse|View View response or JSON payload.
     */
    public function index(ListMemberOrdersRequest $request, MemberOrderPageService $orderPage): JsonResponse|View
    {
        /** @var Room $room */
        $room = $request->attributes->get('room');
        /** @var RoomUser $roomUser */
        $roomUser = $request->attributes->get('room_user');

        if ($request->expectsJson()) {
            $orders = $orderPage->paginatedOrders($room, $roomUser);
            $orders->through(fn (Order $order): array => (new OrderResource($order))->resolve($request));

            return response()->json(['data' => $orders]);
        }

        $filters = $request->filters();

        return view('user.orders', $orderPage->build($room, $roomUser) + [
            'hasLiveCampaign' => $orderPage->roomHasLiveCampaign($room),
            'historyOrders' => $orderPage->history($room, $roomUser, $filters),
            'historyFilters' => $filters,
        ]);
    }

    /**
     * Store a newly created order for the specified campaign.
     *
     * @param StoreOrderRequest $request Validated order store request.
     * @param Room $room Current room model.
     * @param Campaign $campaign Target campaign model.
     * @param CreateOrderAction $action Domain action to create order.
     * @return JsonResponse|RedirectResponse Redirect to orders list or JSON response.
     */
    public function store(StoreOrderRequest $request, Room $room, Campaign $campaign, CreateOrderAction $action, CreateProxyOrdersAction $proxyAction): JsonResponse|RedirectResponse
    {
        try {
            /** @var RoomUser $roomUser */
            $roomUser = $request->attributes->get('room_user');
            $data = $request->validated();
            $hasProxyItems = collect($data['items'] ?? [])->contains(
                static fn(array $item): bool => !empty($item['proxy_user_code'])
            );
            $order = $hasProxyItems
                ? $proxyAction->execute($campaign, $roomUser, $data)
                : $action->execute($campaign, $roomUser, $data);
        } catch (ValidationException $exception) {
            if ($exception->validator->errors()->has('active_order_exists')) {
                /** @var RoomUser|null $roomUser */
                $roomUser = $request->attributes->get('room_user');
                $activeOrder = $roomUser?->orders()
                    ->where('campaign_id', $campaign->id)
                    ->whereIn('status', OrderStatus::activeValues())
                    ->latest()
                    ->first();
                /** @var Room|null $roomAttr */
                $roomAttr = $request->attributes->get('room');

                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => __('global.orders.active_order_exists'),
                        'code' => 'active_order_exists',
                        'order_id' => $activeOrder?->id,
                        // The order page is addressed by code, so the link and the label both use it.
                        'order_code' => $activeOrder?->code,
                        'order_status' => $activeOrder?->status?->value,
                        'order_url' => $activeOrder && $roomAttr ? route('user.orders.page', [$roomAttr, $activeOrder]) : null,
                    ], 422);
                }

                return redirect()->route('user.orders.index', $room->slug)->with('error', __('global.orders.active_order_exists'));
            }
            throw $exception;
        } catch (QueryException $exception) {
            $message = $exception->getMessage();
            if (
                str_contains($message, 'orders_one_active_per_user_campaign')
                || str_contains($message, 'orders_campaign_active_user_unique')
                || str_contains($message, 'active_order_key')
                || str_contains($message, 'UNIQUE constraint failed: orders.campaign_id, orders.room_user_id')
                || str_contains($message, 'UNIQUE constraint failed: orders.campaign_id, orders.active_order_key')
            ) {
                /** @var RoomUser|null $roomUser */
                $roomUser = $request->attributes->get('room_user');
                $activeOrder = $roomUser?->orders()
                    ->where('campaign_id', $campaign->id)
                    ->whereIn('status', OrderStatus::activeValues())
                    ->latest()
                    ->first();
                /** @var Room|null $roomAttr */
                $roomAttr = $request->attributes->get('room');

                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => __('global.orders.active_order_exists'),
                        'code' => 'active_order_exists',
                        'order_id' => $activeOrder?->id,
                        // The order page is addressed by code, so the link and the label both use it.
                        'order_code' => $activeOrder?->code,
                        'order_status' => $activeOrder?->status?->value,
                        'order_url' => $activeOrder && $roomAttr ? route('user.orders.page', [$roomAttr, $activeOrder]) : null,
                    ], 422);
                }

                return redirect()->route('user.orders.index', $room->slug)->with('error', __('global.orders.active_order_exists'));
            }
            throw $exception;
        }

        if ($request->expectsJson()) {
            $request->session()->forget("room_campaign_cart_{$room->id}_{$campaign->id}");
            return response()->json(['data' => (new OrderResource($order))->resolve($request)], 201);
        }

        $request->session()->forget("room_campaign_cart_{$room->id}_{$campaign->id}");
        return redirect()->route('user.orders.index', $room->slug)->with('status', __('room.campaign.order_success'));
    }

    /**
     * Display the specified order details or redirect to order page.
     *
     * @param Request $request Current HTTP request.
     * @param Room $room Current room model.
     * @param Order $order Target order model.
     * @return JsonResponse|View|RedirectResponse JSON payload or redirect response.
     */
    public function show(Request $request, Room $room, Order $order): JsonResponse|View|RedirectResponse
    {
        /** @var RoomUser $roomUser */
        $roomUser = $request->attributes->get('room_user');
        $canViewOrder = $order->room_user_id === $roomUser->id
            || $order->parent()->where('room_user_id', $roomUser->id)->exists();
        abort_unless($order->room_id === $room->id && $canViewOrder, 404);

        if ($request->expectsJson()) {
            $order->load(['items.toppings', 'campaign', 'room']);

            return response()->json(['data' => (new OrderResource($order))->resolve($request)]);
        }

        return redirect()->route('user.orders.index', $room->slug);
    }

    /**
     * Retrieve payment details and QR code for an order.
     *
     * @param Request $request Current HTTP request.
     * @param Room $room Current room model.
     * @param Order $order Target order model.
     * @param ReceivingAccountResolver $accounts Resolves the campaign's receiving account.
     * @return JsonResponse JSON payload containing bank info and QR payment link.
     */
    public function payment(Request $request, Room $room, Order $order, ReceivingAccountResolver $accounts): JsonResponse
    {
        /** @var RoomUser $roomUser */
        $roomUser = $request->attributes->get('room_user');
        abort_unless($order->room_id === $room->id && $order->room_user_id === $roomUser->id, 404);

        // Same rule as the debts page: only the campaign's own active account receives the order payment.
        $account = $accounts->forCampaign($order->campaign);
        abort_unless($account !== null, 404);

        /** @var VietQrService $vietQr */
        $vietQr = app(VietQrService::class);

        $amount = (int) $order->final_amount;
        $content = $vietQr->transferContent((string) $order->code, $roomUser->payerName());

        return response()->json([
            'data' => [
                'bank_code' => $account->bank_code,
                'bank_name' => $account->bank_name,
                'account_number' => $account->account_number,
                'account_name' => $account->account_name,
                'amount' => $amount,
                'transfer_content' => $content,
                'payload' => $vietQr->generate($account, $amount, $content),
            ]
        ]);
    }

    /**
     * Submit payment confirmation for an order to await admin approval.
     *
     * @param Request $request Incoming request.
     * @param Room $room Target room model.
     * @param Order $order Target order model.
     * @param \App\Actions\Order\ConfirmOrderPaymentAction $action Domain action to confirm payment.
     * @return JsonResponse|RedirectResponse Success response.
     */
    public function confirmPayment(Request $request, Room $room, Order $order, \App\Actions\Order\ConfirmOrderPaymentAction $action): JsonResponse|RedirectResponse
    {
        /** @var RoomUser $roomUser */
        $roomUser = $request->attributes->get('room_user');
        abort_unless($order->room_id === $room->id && $order->room_user_id === $roomUser->id, 404);

        $action->execute($order, $roomUser);
        $debt = Debt::query()
            ->where('campaign_id', $order->campaign_id)
            ->where('room_user_id', $roomUser->id)
            ->first();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('room.orders.payment_submitted_success'),
                'payment_status' => 'pending',
                'payment_confirmation' => [
                    'requestedAt' => $debt?->payment_requested_at?->format('d/m/Y H:i'),
                    'content' => $debt?->payment_content ?: $debt?->note,
                    'approvedBy' => null,
                    'approvedAt' => null,
                ],
            ]);
        }

        return redirect()->route('user.orders.index', $room->slug)->with('status', __('room.orders.payment_submitted_success'));
    }
}
