<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\SponsorDrawType;
use App\Http\Requests\Concerns\AuthorizesUserAndAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Settings of a draw session, from the "New session" dialog of the list or the draw page: sponsors (how many, optional target campaign) or shops
 * (suggested names ticked + new names typed one per line).
 */
class ConfigureSponsorDrawRequest extends FormRequest
{
    use AuthorizesUserAndAdmin;

    public function authorize(): bool
    {
        return $this->authorizeActiveRoomAdmin();
    }

    /**
     * The target campaign and the room membership are checked by SponsorDrawService (same room, still open).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $sponsors = $this->input('type') === SponsorDrawType::Sponsors->value;

        return [
            'type' => ['required', Rule::enum(SponsorDrawType::class)],
            'target_winner_count' => [Rule::requiredIf($sponsors), 'nullable', 'integer', 'min:1', 'max:'.max(1, (int) config('sponsor_draw.max_winners', 50))],
            'target_campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
            'shops' => ['nullable', 'array', 'max:'.max(2, (int) config('sponsor_draw.max_shops', 100))],
            'shops.*' => ['string', 'max:120'],
            'new_shops' => ['nullable', 'string', 'max:6000'],
        ];
    }

    /**
     * Typed type of draw.
     */
    public function type(): SponsorDrawType
    {
        return SponsorDrawType::from((string) $this->validated('type'));
    }

    /**
     * Shop names: the ticked suggestions then the typed ones (one per line). Cleaned by SponsorDrawService.
     *
     * @return list<string>
     */
    public function shopNames(): array
    {
        $typed = preg_split('/\r\n|\r|\n/', (string) $this->validated('new_shops', '')) ?: [];

        return array_values(array_map('strval', array_merge((array) $this->validated('shops', []), $typed)));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => __('sponsor_draw.fields.type'),
            'target_winner_count' => __('sponsor_draw.fields.target_winner_count'),
            'target_campaign_id' => __('sponsor_draw.fields.target_campaign'),
            'shops' => __('sponsor_draw.fields.shops'),
            'new_shops' => __('sponsor_draw.fields.new_shops'),
        ];
    }
}
