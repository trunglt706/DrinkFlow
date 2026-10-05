<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Debt\ApproveDebtPaymentAction;
use App\Actions\Order\ConfirmOrderPaymentAction;
use App\Actions\Order\DeleteOrderAction;
use App\Actions\Order\PlaceOrderOnBehalfAction;
use App\Actions\Order\UpdateOrderAction;
use App\Actions\Order\UpdateOrderItemsAction;
use App\Actions\Order\UpdateOrderStatusAction;
use App\Enums\CampaignStatus;
use App\Enums\DebtStatus;
use App\Enums\OrderPlacementType;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\BulkCancelOrdersRequest;
use App\Http\Requests\BulkUpdateOrderStatusRequest;
use App\Http\Requests\PlaceOrderOnBehalfRequest;
use App\Http\Requests\UpdateOrderItemsRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Models\Campaign;
use App\Models\Debt;
use App\Models\Order;
use App\Models\Room;
use App\Services\Admin\AdminOrderOnBehalfService;
use App\Services\Audit\AuditService;
use App\Support\Helpers\DateRangeHelper;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Display a listing of orders as JSON.
     *
     * @param Request $request Parameter value.
     * @return JsonResponse Result of the operation.
     */
    public function index(Request $request): JsonResponse
    {
        $room = $request->attributes->get('room');
        $query = Order::where('room_id', $room->id)->whereNull('parent_id')->with(['roomUser.globalUser', 'parent.roomUser.globalUser', 'children.roomUser.globalUser', 'items.toppings', 'campaign', 'placedByAdmin:id,name'])->latest();
        if ($request->filled('status')) {
            $filterStatus = OrderStatus::tryFrom($request->string('status')->toString());
            if ($filterStatus !== null) {
                $query->where('status', $filterStatus->value);
            }
        }
        if ($request->filled('campaign_id')) $query->where('campaign_id', $request->integer('campaign_id'));
        if ($request->filled('room_user_id')) $query->where('room_user_id', $request->integer('room_user_id'));
        $fromDate = DateRangeHelper::parseCarbonSafe((string) $request->input('from'));
        $toDate = DateRangeHelper::parseCarbonSafe((string) $request->input('to'));
        if ($fromDate) {
            $query->where('created_at', '>=', $fromDate->startOfDay());
        }
        if ($toDate) {
            $query->where('created_at', '<=', $toDate->endOfDay());
        }
        return response()->json(['data' => $query->paginate(\App\Constants\Pagination::ADMIN_PER_PAGE)]);
    }

    /**
     * Display the standalone Orders & Price Adjustments management page.
     *
     * @param Request $request Incoming request.
     * @param Room $room Room entity.
     * @param AdminOrderOnBehalfService $onBehalf Builds the "order on behalf" modal data.
     * @return View Blade view.
     */
    public function page(Request $request, Room $room, AdminOrderOnBehalfService $onBehalf): View
    {
        // Only the latest campaign (running, or the last closed one) is managed on this page.
        $activeCampaign = $room->latestOrderCampaign()?->load('paymentAccount')->loadCount('orders');

        $paymentAccount = $activeCampaign?->paymentAccount
            ?? $room->paymentAccounts()->where('is_default', true)->first()
            ?? $room->paymentAccounts()->first();

        $query = Order::where('room_id', $room->id)
            ->where('campaign_id', $activeCampaign?->id ?? 0)
            ->with(['roomUser.globalUser', 'parent.roomUser.globalUser', 'children.roomUser.globalUser', 'items.toppings', 'campaign', 'placedByAdmin:id,name'])
            ->latest();

        $search = trim($request->string('search')->toString());
        if ($search !== '') {
            $normalizedSearch = mb_strtolower($search);
            $query->where(function (Builder $orderQuery) use ($normalizedSearch, $search): void {
                if (preg_match('/^(?:#?ord-?)?(\d+)$/i', $search, $matches) === 1) {
                    $orderQuery->orWhere('id', (int) $matches[1]);
                }
                $orderQuery
                    ->orWhereRaw('LOWER(code) LIKE ?', ['%' . $normalizedSearch . '%'])
                    ->orWhereRaw('LOWER(note) LIKE ?', ['%' . $normalizedSearch . '%'])
                    ->orWhereHas('campaign', function (Builder $campaignQuery) use ($normalizedSearch): void {
                        $campaignQuery->whereRaw('LOWER(name) LIKE ?', ['%' . $normalizedSearch . '%'])
                            ->orWhereRaw('LOWER(restaurant) LIKE ?', ['%' . $normalizedSearch . '%']);
                    })
                    ->orWhereHas('roomUser', function (Builder $roomUserQuery) use ($normalizedSearch): void {
                        $roomUserQuery->whereRaw('LOWER(display_name) LIKE ?', ['%' . $normalizedSearch . '%'])
                            ->orWhereRaw('LOWER(user_code) LIKE ?', ['%' . $normalizedSearch . '%'])
                            ->orWhereHas('globalUser', function (Builder $userQuery) use ($normalizedSearch): void {
                                $userQuery->whereRaw('LOWER(name) LIKE ?', ['%' . $normalizedSearch . '%'])
                                    ->orWhereRaw('LOWER(email) LIKE ?', ['%' . $normalizedSearch . '%']);
                            });
                    })
                    ->orWhereHas('items', function (Builder $itemQuery) use ($normalizedSearch): void {
                        $itemQuery->whereRaw('LOWER(item_name) LIKE ?', ['%' . $normalizedSearch . '%'])
                            ->orWhereRaw('LOWER(size_name) LIKE ?', ['%' . $normalizedSearch . '%'])
                            ->orWhereHas('toppings', function (Builder $toppingQuery) use ($normalizedSearch): void {
                                $toppingQuery->whereRaw('LOWER(topping_name) LIKE ?', ['%' . $normalizedSearch . '%']);
                            });
                    });
            });
        }

        $selectedStatus = $request->string('status')->toString() ?: 'all';
        $status = OrderStatus::tryFrom($selectedStatus);
        if ($status !== null) {
            $query->where('status', $status->value);
        } elseif ($selectedStatus !== 'all') {
            $selectedStatus = 'all';
        }

        $placementType = OrderPlacementType::tryFrom($request->string('type')->toString());
        if ($placementType !== null) {
            $query->ofPlacementType($placementType);
        }

        $orders = $query->paginate(\App\Constants\Pagination::ADMIN_PER_PAGE)->withQueryString();

        return view('admin.orders', [
            'room' => $room,
            'orders' => $orders,
            'activeCampaign' => $activeCampaign,
            'paymentAccount' => $paymentAccount,
            'onBehalfData' => $onBehalf->formData($room, $activeCampaign),
            'statusFilters' => collect(OrderStatus::cases())->map(static fn (OrderStatus $status): array => [
                'value' => $status->value,
                'label' => __('admin.status_'.match ($status) {
                    OrderStatus::Submitted => 'pending',
                    OrderStatus::Completed => 'paid',
                    default => $status->value,
                }),
            ])->values()->all(),
            'typeFilters' => collect(OrderPlacementType::cases())->map(static fn (OrderPlacementType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ])->values()->all(),
            'filters' => [
                'search' => $search,
                'status' => $selectedStatus,
                'type' => $placementType?->value ?? 'all',
            ],
        ]);
    }

    /**
     * Place an order for a room member on their behalf (admin "đặt dùm").
     *
     * @param PlaceOrderOnBehalfRequest $request Validated request (campaign_id, room_user_id, items[], note).
     * @param Room $room Current room.
     * @param PlaceOrderOnBehalfAction $action Domain action creating the member's order.
     * @return JsonResponse Created order summary.
     */
    public function storeOnBehalf(PlaceOrderOnBehalfRequest $request, Room $room, PlaceOrderOnBehalfAction $action): JsonResponse
    {
        $data = $request->validated();
        $campaign = $room->campaigns()->findOrFail((int) $data['campaign_id']);
        $member = $room->roomUsers()->with('globalUser')->findOrFail((int) $data['room_user_id']);

        $order = $action->execute($room, $campaign, $member, $request->user('admin'), $data);

        return response()->json([
            'message' => __('admin.on_behalf_success', [
                'code' => $order->code,
                'member' => $member->payerName(),
            ]),
            'data' => ['id' => $order->id, 'code' => $order->code],
        ], 201);
    }

    /**
     * Display the specified order details as JSON.
     *
     * @param Room $room Parameter value.
     * @param Order $order Parameter value.
     * @return JsonResponse Result of the operation.
     */
    public function show(Room $room, Order $order): JsonResponse
    {
        $this->assertRoom($order);
        return response()->json(['data' => $order->loadMissing(['roomUser.globalUser', 'parent.roomUser.globalUser', 'children.roomUser.globalUser', 'items.toppings', 'campaign'])]);
    }

    /**
     * Handle the update item price operation.
     * @param UpdateOrderRequest $request Parameter value.
     * @param Room $room Parameter value.
     * @param Order $order Parameter value.
     * @param UpdateOrderAction $action Parameter value.
     * @return JsonResponse Result of the operation.
     */
    public function update(UpdateOrderRequest $request, Room $room, Order $order, UpdateOrderAction $action): JsonResponse
    {
        $this->assertRoom($order);
        return response()->json(['data' => $action->execute($order, $request->validated())]);
    }

    /**
     * Replace the items of a member's order (admin "sửa đơn"): quantities, sizes, toppings, self-paid flags and note.
     *
     * @param UpdateOrderItemsRequest $request Validated request (items[], note).
     * @param Room $room Current room.
     * @param Order $order Order being edited.
     * @param UpdateOrderItemsAction $action Domain action re-pricing and saving the items.
     * @return JsonResponse Updated order.
     */
    public function updateItems(UpdateOrderItemsRequest $request, Room $room, Order $order, UpdateOrderItemsAction $action): JsonResponse
    {
        $this->assertRoom($order);
        $order = $action->execute($order, $request->user('admin'), $request->validated());

        return response()->json([
            'message' => __('admin.edit_order_success', ['code' => $order->code]),
            'data' => $order,
        ]);
    }

    /**
     * Handle the update status operation.
     * @param UpdateOrderStatusRequest $request Parameter value.
     * @param Room $room Parameter value.
     * @param Order $order Parameter value.
     * @param UpdateOrderStatusAction $action Parameter value.
     * @return JsonResponse Result of the operation.
     */
    public function updateStatus(UpdateOrderStatusRequest $request, Room $room, Order $order, UpdateOrderStatusAction $action): JsonResponse
    {
        $this->assertRoom($order);
        return response()->json(['data' => $action->execute($order, $request->validated('status'))]);
    }

    /**
     * Batch update status for multiple orders.
     *
     * @param BulkUpdateOrderStatusRequest $request Incoming validated request.
     * @param Room $room Target room.
     * @param UpdateOrderStatusAction $action Status transition action.
     * @return JsonResponse Result with count of updated orders.
     */
    public function bulkStatus(BulkUpdateOrderStatusRequest $request, Room $room, UpdateOrderStatusAction $action, AuditService $audit): JsonResponse
    {
        $orderIds = $request->validated('order_ids');
        $targetStatus = (string) $request->validated('status');

        $orders = Order::where('room_id', $room->id)
            ->whereIn('id', $orderIds)
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->whereNull('cancelled_at')
            ->get();

        $updatedCount = 0;
        foreach ($orders as $order) {
            $statusValue = $order->status instanceof \BackedEnum ? $order->status->value : (string) $order->status;
            if ($statusValue === OrderStatus::Cancelled->value || $order->cancelled_at !== null || $statusValue === $targetStatus) {
                continue;
            }
            try {
                $before = ['status' => $statusValue];
                $action->execute($order, $targetStatus);
                $audit->record('order.bulk_status_updated', 'order', $order->id, $room->id, $before, ['status' => $targetStatus], ['bulk' => true]);
                $updatedCount++;
            } catch (\Throwable) {
                // Skip invalid transitions for individual orders in bulk mode
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'updated_count' => $updatedCount,
            ],
            'updated_count' => $updatedCount,
            'message' => __('admin.bulk_status_updated_success', ['count' => $updatedCount]),
        ]);
    }

    /**
     * Batch cancel multiple orders.
     *
     * @param BulkCancelOrdersRequest $request Incoming validated request.
     * @param Room $room Target room.
     * @param UpdateOrderStatusAction $action Status transition action.
     * @return JsonResponse Result with count of cancelled orders.
     */
    public function bulkCancel(BulkCancelOrdersRequest $request, Room $room, UpdateOrderStatusAction $action, AuditService $audit): JsonResponse
    {
        $orderIds = $request->validated('order_ids');

        $orders = Order::where('room_id', $room->id)
            ->whereIn('id', $orderIds)
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->whereNull('cancelled_at')
            ->get();

        $cancelledCount = 0;
        foreach ($orders as $order) {
            $statusValue = $order->status instanceof \BackedEnum ? $order->status->value : (string) $order->status;
            if ($statusValue === OrderStatus::Cancelled->value || $order->cancelled_at !== null) {
                continue;
            }
            try {
                $before = ['status' => $statusValue];
                $action->execute($order, OrderStatus::Cancelled->value);
                $audit->record('order.bulk_cancelled', 'order', $order->id, $room->id, $before, ['status' => OrderStatus::Cancelled->value], ['bulk' => true]);
                $cancelledCount++;
            } catch (\Throwable) {
                // Skip invalid transitions for individual orders in bulk mode
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'cancelled_count' => $cancelledCount,
            ],
            'cancelled_count' => $cancelledCount,
            'message' => __('admin.bulk_cancelled_success', ['count' => $cancelledCount]),
        ]);
    }

    /**
     * Handle the cancel operation.
     * @param Room $room Parameter value.
     * @param Order $order Parameter value.
     * @param UpdateOrderStatusAction $action Parameter value.
     * @return JsonResponse Result of the operation.
     */
    public function cancel(Room $room, Order $order, UpdateOrderStatusAction $action): JsonResponse
    {
        $this->assertRoom($order);
        if ($order->status === OrderStatus::Cancelled || $order->status?->value === OrderStatus::Cancelled->value) {
            return response()->json(['data' => $order]);
        }
        return response()->json(['data' => $action->execute($order, OrderStatus::Cancelled->value)]);
    }

    /**
     * Handle the unlock operation.
     * @param Room $room Parameter value.
     * @param Order $order Parameter value.
     * @param UpdateOrderStatusAction $action Parameter value.
     * @return JsonResponse Result of the operation.
     */
    public function unlock(Room $room, Order $order, UpdateOrderStatusAction $action): JsonResponse
    {
        $this->assertRoom($order);
        if ($order->status->isActive()) {
            $order = $action->execute($order, OrderStatus::Cancelled->value);
        }
        return response()->json(['data' => ['unlocked' => true, 'order' => $order]]);
    }

    /**
     * Handle the destroy operation. Only allowed while the order's campaign is still live.
     *
     * @param Room $room Parameter value.
     * @param Order $order Parameter value.
     * @param DeleteOrderAction $action Parameter value.
     * @return JsonResponse Result of the operation.
     */
    public function destroy(Room $room, Order $order, DeleteOrderAction $action): JsonResponse
    {
        $this->assertRoom($order);
        $this->assertCampaignLive($order);
        $action->execute($order);
        return response()->json(['message' => 'order_deleted']);
    }

    /**
     * Confirm payment for an order and settle the member's outstanding debt for its campaign.
     *
     * Approving the debt (creating it first if none exists yet) marks every order this member
     * placed in the same campaign as paid, matching how debts are modeled room_user+campaign-wide
     * rather than per order.
     *
     * @param Room $room Room entity.
     * @param Order $order Order to confirm payment for.
     * @param ConfirmOrderPaymentAction $action Domain action to confirm order payment.
     * @return JsonResponse Result of the operation.
     */
    public function confirmPayment(Room $room, Order $order, ConfirmOrderPaymentAction $action): JsonResponse
    {
        $this->assertRoom($order);
        $this->assertCampaignLive($order);

        $updatedOrder = $action->execute($order);

        return response()->json([
            'message' => __('admin.confirm_order_payment_success'),
            'data' => $updatedOrder,
        ]);
    }

    /**
     * Handle the assert room operation.
     * @param Order $order Parameter value.
     * @return void Result of the operation.
     */
    private function assertRoom(Order $order): void
    {
        abort_unless($order->room_id === request()->attributes->get('room')->id, 404);
    }

    /**
     * Guard an order mutation to campaigns that are still live (active).
     *
     * @param Order $order Order whose campaign status is checked.
     * @return void
     */
    private function assertCampaignLive(Order $order): void
    {
        abort_unless($order->campaign?->status === CampaignStatus::Active, 422, __('admin.campaign_locked_cannot_modify'));
    }
}
