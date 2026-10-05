<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Global;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\GlobalUser;
use App\Exports\PaymentStatementExport;
use App\Services\Payment\UserPaymentsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PaymentsController extends Controller
{
    /**
     * Download the current user's statement using the selected filters and sort.
     *
     * @param Request $request Current authenticated request.
     * @param UserPaymentsService $service Payment data service.
     * @return BinaryFileResponse XLSX attachment containing personal orders only.
     */
    public function export(Request $request, UserPaymentsService $service): BinaryFileResponse
    {
        /** @var GlobalUser $user */
        $user = $request->attributes->get('global_user') ?? $request->user('web');
        $data = $service->getPaymentsData($user, $request);

        return Excel::download(
            new PaymentStatementExport($data['displayedOrders']),
            'drinkflow-statement-' . now()->format('Y-m-d-His') . '.xlsx'
        );
    }

    /**
     * Hiển thị trang quản lý tài khoản thanh toán và đối soát công nợ cá nhân (/me/payments).
     *
     * @param  \Illuminate\Http\Request  $request  Đối tượng HTTP Request hiện tại
     * @param  \App\Services\Payment\UserPaymentsService|null  $service  Service xử lý dữ liệu thanh toán
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\JsonResponse  Giao diện View hoặc phản hồi JSON
     */
    public function index(Request $request, ?UserPaymentsService $service = null): View|JsonResponse
    {
        $service = $service ?? app(UserPaymentsService::class);

        /** @var GlobalUser $user */
        $user = $request->attributes->get('global_user') ?? $request->user('web');

        $data = $service->getPaymentsData($user, $request);

        if ($request->wantsJson()) {
            return response()->json([
                'metrics' => [
                    'total_unpaid' => $data['totalUnpaidAmount'],
                    'unpaid_count' => $data['unpaidCount'],
                    'paid_this_month' => $data['paidThisMonthAmount'],
                    'paid_count' => $data['paidThisMonthCount'],
                    'sponsor_received' => $data['totalSponsorReceived'],
                ],
                'orders' => OrderResource::collection($data['displayedOrders']->values())->resolve($request),
                'default_bank' => $data['defaultBank'],
            ]);
        }

        return view('user.global.payments', $data);
    }
}
