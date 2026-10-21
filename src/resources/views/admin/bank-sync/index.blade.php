<x-admin.layout :title="__('bank_sync.title')" active="bank-sync" :room="$room">
    @php
        $money = static fn (int $amount): string => \App\Support\Helpers\FormatHelper::formatCurrency($amount);
        $timezone = (string) ($room->timezone ?: config('app.display_timezone', config('app.timezone')));
        $statusStyles = [
            'matched' => 'bg-emerald-100 text-emerald-800',
            'manual' => 'bg-sky-100 text-sky-800',
            'unmatched' => 'bg-amber-100 text-amber-800',
            'ignored' => 'bg-surface-container text-on-surface-variant',
        ];
    @endphp
    <div class="space-y-6">
        <section class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-outline-variant/40">
            <div>
                <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight text-on-surface">
                    {{ __('bank_sync.title') }}
                    @if ($unmatchedCount > 0)
                        <span data-bank-unmatched="{{ $unmatchedCount }}" class="inline-flex min-w-[24px] items-center justify-center rounded-full bg-amber-500 px-2 py-0.5 text-xs font-bold text-white"
                            title="{{ __('bank_sync.unmatched_badge', ['count' => $unmatchedCount]) }}">{{ $unmatchedCount }}</span>
                    @endif
                </h1>
                <p class="mt-1 max-w-3xl text-xs text-outline">{{ __('bank_sync.description') }}</p>
            </div>
        </section>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-error/30 bg-error-container p-3 text-sm text-on-error-container" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5" data-bank-settings>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-bold text-on-surface">{{ __('bank_sync.settings_title') }}</h2>
                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $integration->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-surface-container text-on-surface-variant' }}">
                    {{ $integration->is_active ? __('bank_sync.state_active') : __('bank_sync.state_inactive') }}
                </span>
            </div>
            <ol class="mt-3 list-decimal space-y-1 pl-5 text-xs text-on-surface-variant">
                <li>{{ __('bank_sync.step_1') }}</li>
                <li>{{ __('bank_sync.step_2') }}</li>
                <li>{{ __('bank_sync.step_3') }}</li>
            </ol>
            <div class="mt-4 space-y-1.5">
                <label for="bank-webhook-url" class="block text-xs font-semibold text-on-surface">{{ __('bank_sync.webhook_url') }}</label>
                <input id="bank-webhook-url" type="text" readonly value="{{ $webhookUrl }}" onclick="this.select()"
                    class="h-10 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 font-mono text-xs">
                <p class="text-[11px] text-outline">{{ __('bank_sync.webhook_url_hint') }}</p>
            </div>
            <form method="POST" action="{{ route('admin.bank-sync.settings', $room) }}" class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                @csrf
                @method('PUT')
                <div class="space-y-1.5">
                    <label for="bank-api-key" class="block text-xs font-semibold text-on-surface">{{ __('bank_sync.api_key') }}</label>
                    <input id="bank-api-key" name="api_key" type="password" autocomplete="off" maxlength="255"
                        placeholder="{{ filled($integration->api_key) ? __('bank_sync.api_key_saved') : __('bank_sync.api_key_placeholder') }}"
                        class="h-10 w-full rounded-lg border border-outline-variant bg-surface px-3 text-xs">
                </div>
                <div class="flex flex-col justify-end gap-2 text-xs text-on-surface">
                    <label class="inline-flex items-center gap-2">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked($integration->is_active) class="rounded border-outline-variant text-primary">
                        {{ __('bank_sync.enable') }}
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="regenerate_token" value="1" class="rounded border-outline-variant text-primary">
                        {{ __('bank_sync.regenerate_token') }}
                    </label>
                </div>
                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-xs font-bold text-on-primary hover:bg-primary/90">
                        <span class="material-symbols-outlined text-[18px]">save</span>{{ __('admin.save_changes_btn') }}
                    </button>
                </div>
            </form>
            @if ($integration->last_received_at)
                <p class="mt-2 text-[11px] text-outline">{{ __('bank_sync.last_received', ['time' => $integration->last_received_at->copy()->timezone($timezone)->format('H:i d/m/Y')]) }}</p>
            @endif
        </section>

        <section class="rounded-xl border border-outline-variant bg-surface-container-lowest overflow-hidden">
            <nav class="flex flex-wrap gap-2 border-b border-outline-variant/60 p-3" aria-label="{{ __('bank_sync.filter_label') }}">
                <a href="{{ route('admin.bank-sync.index', $room) }}"
                    class="rounded-full px-3 py-1 text-xs font-semibold {{ $status === null ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high' }}">{{ __('bank_sync.filter_all') }}</a>
                @foreach (\App\Enums\BankTransactionStatus::cases() as $case)
                    <a href="{{ route('admin.bank-sync.index', ['room' => $room, 'status' => $case->value]) }}"
                        class="rounded-full px-3 py-1 text-xs font-semibold {{ $status === $case ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high' }}">{{ __('bank_sync.statuses.'.$case->value) }}</a>
                @endforeach
            </nav>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-surface-container-low text-[11px] uppercase tracking-wider text-outline">
                        <tr>
                            <th class="px-5 py-3 text-left">{{ __('bank_sync.col_time') }}</th>
                            <th class="px-5 py-3 text-left">{{ __('bank_sync.col_content') }}</th>
                            <th class="px-5 py-3 text-right">{{ __('bank_sync.col_amount') }}</th>
                            <th class="px-5 py-3 text-left">{{ __('bank_sync.col_status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/60">
                        @forelse ($transactions as $transaction)
                            <tr data-bank-transaction="{{ $transaction->status->value }}">
                                <td class="px-5 py-3 whitespace-nowrap font-mono text-xs text-on-surface">
                                    {{ ($transaction->transacted_at ?? $transaction->created_at)?->copy()->timezone($timezone)->format('d/m/Y H:i') }}
                                    @if ($transaction->reference_code)
                                        <small class="block text-[11px] text-outline">{{ $transaction->reference_code }}</small>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-xs text-on-surface">
                                    <span class="break-all font-mono">{{ $transaction->content }}</span>
                                    @if ($transaction->roomUser)
                                        <span class="block text-on-surface-variant">{{ $transaction->roomUser->display_name }}@if ($transaction->debt) · {{ $transaction->debt->code }}@endif</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right font-mono font-bold whitespace-nowrap {{ $transaction->direction === 'in' ? 'text-emerald-700' : 'text-red-700' }}">
                                    {{ $transaction->direction === 'in' ? '+' : '-' }}{{ $money((int) $transaction->amount) }}
                                </td>
                                <td class="px-5 py-3 text-xs">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 font-semibold {{ $statusStyles[$transaction->status->value] }}">{{ __('bank_sync.statuses.'.$transaction->status->value) }}</span>
                                    @if ($transaction->reason)
                                        <small class="mt-0.5 block text-[11px] text-outline">{{ __('bank_sync.reasons.'.$transaction->reason) }}</small>
                                    @endif
                                    @if ($transaction->status === \App\Enums\BankTransactionStatus::Unmatched)
                                        <div class="mt-2 flex flex-wrap items-center gap-2">
                                            <form method="POST" action="{{ route('admin.bank-sync.assign', [$room, $transaction->id]) }}" class="flex items-center gap-1" data-bank-assign>
                                                @csrf
                                                <input name="debt_code" type="text" required maxlength="40" value="{{ $transaction->debt?->code }}"
                                                    placeholder="{{ __('bank_sync.debt_code') }}" aria-label="{{ __('bank_sync.debt_code') }}"
                                                    class="h-8 w-32 rounded-lg border border-outline-variant bg-surface px-2 font-mono text-[11px]">
                                                <button type="submit" class="h-8 rounded-lg bg-primary px-2.5 text-[11px] font-bold text-on-primary">{{ __('bank_sync.assign') }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.bank-sync.ignore', [$room, $transaction->id]) }}">
                                                @csrf
                                                <button type="submit" class="h-8 rounded-lg border border-outline-variant px-2.5 text-[11px] font-semibold text-on-surface-variant hover:bg-surface-container-low">{{ __('bank_sync.ignore') }}</button>
                                            </form>
                                        </div>
                                    @elseif ($transaction->resolver)
                                        <small class="mt-0.5 block text-[11px] text-outline">{{ $transaction->resolver->name }}</small>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-10 text-center text-sm text-outline">{{ __('bank_sync.empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($transactions->hasPages())
                <div class="border-t border-outline-variant/60 p-3">{{ $transactions->links() }}</div>
            @endif
        </section>
    </div>
</x-admin.layout>
