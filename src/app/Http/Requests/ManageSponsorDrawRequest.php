<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesUserAndAdmin;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin action on a sponsor draw session (draw, new link, confirm, resend, cancel with a reason).
 */
class ManageSponsorDrawRequest extends FormRequest
{
    use AuthorizesUserAndAdmin;

    public function authorize(): bool
    {
        return $this->authorizeActiveRoomAdmin();
    }

    /**
     * The client never chooses a winner: only a cancellation carries data (its reason).
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return match (true) {
            $this->routeIs('admin.sponsor-draws.cancel') => ['reason' => ['required', 'string', 'min:3', 'max:500']],
            $this->routeIs('admin.sponsor-draws.eligible.remove') => ['position' => ['required', 'integer', 'min:0']],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['reason' => __('sponsor_draw.fields.cancel_reason')];
    }
}
