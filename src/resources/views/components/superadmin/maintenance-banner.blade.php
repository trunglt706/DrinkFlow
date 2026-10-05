{{-- Warns a superadmin (who bypasses maintenance) that everyone else currently sees the maintenance page,
     or that maintenance is scheduled. Renders nothing for other admins or when maintenance is off. --}}
@php
    $viewer = request()->user('superadmin');
    $maintenance = $viewer !== null ? app(\App\Services\System\SystemSettingsService::class)->maintenanceState() : null;
    $dateFormat = 'H:i d/m/Y';
@endphp
@if ($maintenance && ($maintenance['active'] || $maintenance['scheduled']))
    <div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2.5 text-xs font-semibold border-b '.($maintenance['active'] ? 'bg-amber-100 text-amber-900 border-amber-300 dark:bg-amber-400/15 dark:text-amber-200 dark:border-amber-400/30' : 'bg-sky-50 text-sky-900 border-sky-200 dark:bg-sky-400/15 dark:text-sky-200 dark:border-sky-400/30')]) }}
        role="status" data-maintenance-banner="{{ $maintenance['active'] ? 'active' : 'scheduled' }}">
        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">{{ $maintenance['active'] ? 'construction' : 'schedule' }}</span>
        <span class="flex-1 min-w-0">
            @if ($maintenance['active'])
                {{ $maintenance['ends'] ? __('superadmin.maintenance_banner.active_until', ['time' => $maintenance['ends']->format($dateFormat)]) : __('superadmin.maintenance_banner.active') }}
            @else
                {{ __('superadmin.maintenance_banner.scheduled', ['time' => $maintenance['starts']->format($dateFormat)]) }}
            @endif
        </span>
        @can('settings.view')
        <a href="{{ route('superadmin.system.page') }}" class="inline-flex items-center gap-1 underline underline-offset-2 hover:no-underline">
            {{ __('superadmin.maintenance_banner.manage') }}<span class="material-symbols-outlined text-[16px]" aria-hidden="true">arrow_forward</span>
        </a>
        @endcan
    </div>
@endif
