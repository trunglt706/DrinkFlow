<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Order;
use App\Support\Helpers\FormatHelper;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class PaymentStatementExport extends DefaultValueBinder implements FromGenerator, ShouldAutoSize, WithColumnFormatting, WithCustomValueBinder, WithHeadings, WithMapping
{
    /** Rows fetched per database round trip when streaming a query-backed export. */
    private const CHUNK_SIZE = 500;

    /**
     * @param Builder<Order>|Collection<int, Order> $orders Query streamed in chunks for real downloads, or an
     *        in-memory collection exported as-is (no database access, e.g. previews and tests).
     */
    public function __construct(private readonly Builder|Collection $orders)
    {
    }

    /**
     * Yield the orders to export; query-backed exports are read lazily in chunks so large statements stay
     * within memory limits.
     *
     * @return Generator<int, Order>
     */
    public function generator(): Generator
    {
        $orders = $this->orders instanceof Builder ? (clone $this->orders)->lazy(self::CHUNK_SIZE) : $this->orders;

        foreach ($orders as $order) {
            yield $order;
        }
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
     * Mapped rows for lightweight assertions; real downloads stream through generator().
     *
     * @return array<int, array<int, string|float>>
     */
    public function array(): array
    {
        return array_map(fn (Order $order): array => $this->map($order), iterator_to_array($this->generator(), false));
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
