<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Order;
use App\Support\Helpers\FormatHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class PaymentStatementExport extends DefaultValueBinder implements FromQuery, ShouldAutoSize, WithColumnFormatting, WithCustomValueBinder, WithHeadings, WithMapping
{
    /** @var Builder<Order> */
    private readonly Builder $orders;

    /** @var Collection<int, Order>|null */
    private readonly ?Collection $sourceCollection;

    /** @param Builder<Order>|Collection<int, Order> $orders */
    public function __construct(Builder|Collection $orders)
    {
        $this->sourceCollection = $orders instanceof Collection ? $orders : null;
        $this->orders = $orders instanceof Builder
            ? $orders
            : Order::query()->whereKey($orders->pluck('id')->filter()->all())->with(['room', 'campaign']);
    }

    /** @return Builder<Order> */
    public function query(): Builder
    {
        return clone $this->orders;
    }

    /** @return array<int, string|float> */
    public function map($order): array
    {
        return [
            '#'.$order->code,
            $order->created_at ? FormatHelper::formatDateTime($order->created_at, 'd/m/Y H:i') : '',
            $order->room?->name ?? '',
            $order->campaign?->restaurant ?: ($order->campaign?->name ?? ''),
            (float) $order->subtotal,
            (float) $order->sponsor_amount,
            (float) $order->final_amount,
            __('payment_statement.statuses.'.$order->status->value),
        ];
    }

    /**
     * Retained for lightweight unit assertions; real downloads use chunked FromQuery processing.
     *
     * @return array<int, array<int, string|float>>
     */
    public function array(): array
    {
        $orders = $this->sourceCollection ?? $this->query()->get();

        return $orders->map(fn (Order $order): array => $this->map($order))->all();
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return array_map(static fn (string $key): string => __($key), [
            'global.dashboard.col_code',
            'global.dashboard.col_time',
            'global.dashboard.col_room',
            'global.dashboard.col_restaurant',
            'global.orders.th_subtotal',
            'global.payments.total_sponsor_received',
            'global.dashboard.col_amount',
            'global.dashboard.col_status',
        ]);
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        return ['E' => '#,##0', 'F' => '#,##0', 'G' => '#,##0'];
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
