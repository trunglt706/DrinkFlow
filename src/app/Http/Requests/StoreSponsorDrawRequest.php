<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesUserAndAdmin;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin form creating a sponsor draw session (source campaign, number of sponsors, optional next campaign).
 */
class StoreSponsorDrawRequest extends FormRequest
{
    use AuthorizesUserAndAdmin;

    public function authorize(): bool
    {
        return $this->authorizeActiveRoomAdmin();
    }

    /**
     * Campaign IDs are checked against the room by SponsorDrawService (same room, target still open).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'campaign_id' => ['required', 'integer', 'exists:campaigns,id'],
            'target_winner_count' => ['required', 'integer', 'min:1', 'max:'.max(1, (int) config('sponsor_draw.max_winners', 50))],
            'target_campaign_id' => ['nullable', 'integer', 'exists:campaigns,id', 'different:campaign_id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'campaign_id' => __('sponsor_draw.fields.campaign'),
            'target_winner_count' => __('sponsor_draw.fields.target_winner_count'),
            'target_campaign_id' => __('sponsor_draw.fields.target_campaign'),
        ];
    }
}
