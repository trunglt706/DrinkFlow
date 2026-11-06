<?php

declare(strict_types=1);

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    /**
     * Handle the index operation.
     * @param Request $request Parameter value.
     * @return JsonResponse Result of the operation.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query()->with('room:id,name,slug')->latest('created_at')->latest('id');
        foreach (['actor_type', 'event', 'target_type', 'room_id'] as $field)
            if ($request->filled($field))
                $query->where($field, $request->input($field));
        if ($request->filled('actor_id'))
            $query->where('actor_id', $request->integer('actor_id'));
        if ($request->filled('from'))
            $query->whereDate('created_at', '>=', $request->date('from'));
        if ($request->filled('to'))
            $query->whereDate('created_at', '<=', $request->date('to'));
        return response()->json(['data' => $query->paginate(\App\Constants\Pagination::ADMIN_PER_PAGE)]);
    }
}
