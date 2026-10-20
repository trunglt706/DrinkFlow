<?php

declare(strict_types=1);

namespace App\Exports;

use App\Exports\Concerns\NeutralizesFormulas;
use App\Models\RoomFundTransaction;
use App\Support\Helpers\FormatHelper;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Room fund ledger as a spreadsheet, newest entry first.
 */
final class RoomFundLedgerExport implements FromQuery, WithMapping, WithHeadings, ShouldAutoSize
{
    use NeutralizesFormulas;

    /** @param Builder<RoomFundTransaction> $entries Ledger query (RoomFundService::history()). */
    public function __construct(private readonly Builder $entries)
    {
    }

    /** @return Builder<RoomFundTransaction> */
    public function query(): Builder
    {
        return clone $this->entries;
    }

    /**
     * @param RoomFundTransaction $entry
     * @return array<int, mixed>
     */
    public function map($entry): array
    {
        return self::neutralizeRow([
            $entry->created_at ? FormatHelper::formatDateTime($entry->created_at, 'd/m/Y H:i') : '',
            __('room_fund.types.'.$entry->type->value),
            $entry->amount,
            $entry->balance_after,
            $entry->roomUser?->display_name ?? '',
            $entry->debt?->campaign?->name ?? '',
            $entry->note ?? '',
            $entry->creator?->name ?? '',
        ]);
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return array_map(static fn (string $key): string => __('room_fund.'.$key), [
            'col_time', 'col_type', 'col_amount', 'col_balance', 'col_member', 'col_campaign', 'col_note', 'col_admin',
        ]);
    }
}
