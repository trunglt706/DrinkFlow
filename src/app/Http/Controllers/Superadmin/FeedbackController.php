<?php

declare(strict_types=1);

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SetFeedbackStatusRequest;
use App\Http\Requests\UpdateFeedbackRequest;
use App\Models\Feedback;
use App\Services\Audit\AuditService;
use Illuminate\Http\JsonResponse;

class FeedbackController extends Controller
{
    /**
     * Approve (active) or take down (inactive) a feedback entry.
     *
     * @param SetFeedbackStatusRequest $request Validated request carrying the new status.
     * @param Feedback $feedback Feedback being moderated.
     * @param AuditService $audit Audit trail service.
     * @return JsonResponse Updated feedback id and status.
     */
    public function status(SetFeedbackStatusRequest $request, Feedback $feedback, AuditService $audit): JsonResponse
    {
        $before = ['status' => $feedback->status->value];
        $feedback->update(['status' => $request->validated('status')]);
        $feedback->refresh();
        $audit->record('feedback.status_changed', 'feedback', $feedback->id, null, $before, ['status' => $feedback->status->value], ['rating' => $feedback->rating]);

        return response()->json(['data' => ['id' => $feedback->id, 'status' => $feedback->status->value]]);
    }

    /**
     * Correct a feedback entry (content, rating, subsystem, display name); the status is kept.
     *
     * @param UpdateFeedbackRequest $request Validated corrections.
     * @param Feedback $feedback Feedback being edited.
     * @param AuditService $audit Audit trail service.
     * @return JsonResponse Updated feedback.
     */
    public function update(UpdateFeedbackRequest $request, Feedback $feedback, AuditService $audit): JsonResponse
    {
        $fields = ['rating', 'subsystem', 'content', 'user_display_name'];
        $before = $feedback->only($fields);
        $feedback->update($request->validated());
        $after = $feedback->refresh()->only($fields);
        $audit->record('feedback.updated', 'feedback', $feedback->id, null, $before, $after);

        return response()->json(['data' => ['id' => $feedback->id] + $after]);
    }

    /**
     * Delete a feedback entry permanently (spam, abusive or duplicate content).
     *
     * @param Feedback $feedback Feedback being deleted.
     * @param AuditService $audit Audit trail service.
     * @return JsonResponse Deleted feedback id.
     */
    public function destroy(Feedback $feedback, AuditService $audit): JsonResponse
    {
        $snapshot = $feedback->only(['rating', 'subsystem', 'content', 'status', 'user_display_name', 'global_user_id']);
        $id = $feedback->id;
        $feedback->delete();
        $audit->record('feedback.deleted', 'feedback', $id, null, $snapshot, []);

        return response()->json(['data' => ['id' => $id]]);
    }
}
