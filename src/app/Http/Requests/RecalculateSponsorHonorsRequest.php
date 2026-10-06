<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesUserAndAdmin;
use Illuminate\Foundation\Http\FormRequest;

class RecalculateSponsorHonorsRequest extends FormRequest
{
    use AuthorizesUserAndAdmin;

    /**
     * Only an active admin who may operate the (active) room can rebuild its sponsor podium.
     */
    public function authorize(): bool
    {
        return $this->authorizeActiveRoomAdmin();
    }

    /**
     * The action takes no input: it always rebuilds the previous calendar month.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
