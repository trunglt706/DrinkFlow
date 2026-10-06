{{-- Stepper of the admin password recovery flow (forgot password → OTP → new password), same look as the
     registration stepper. `current` is the step being shown (1, 2 or 3); earlier steps show a check mark. --}}
@props(['current' => 1])
@php
    $recoverySteps = [1 => 'admin.recovery_step_email', 2 => 'admin.recovery_step_otp', 3 => 'admin.recovery_step_password'];
@endphp
<ol class="flex items-center justify-center gap-2 text-[11px] text-outline mb-7" aria-label="{{ __('admin.recovery_steps_label') }}">
    @foreach ($recoverySteps as $number => $labelKey)
        @if ($number > 1)
            <li @class(['w-5 sm:w-8 h-px', 'bg-primary/40' => $number <= $current, 'bg-emerald-200' => $number > $current]) aria-hidden="true"></li>
        @endif
        <li class="flex items-center gap-1.5" @if ($number === $current) aria-current="step" @endif>
            @if ($number < $current)
                <b class="w-6 h-6 grid place-items-center rounded-full bg-emerald-100 text-primary">
                    <span class="material-symbols-outlined !text-[15px] font-bold" aria-hidden="true">check</span>
                </b>
                <span class="font-medium">{{ __($labelKey) }}</span>
            @elseif ($number === $current)
                <b class="w-6 h-6 grid place-items-center rounded-full bg-primary text-on-primary text-[11px]">{{ $number }}</b>
                <span class="font-semibold text-on-surface">{{ __($labelKey) }}</span>
            @else
                <b class="w-6 h-6 grid place-items-center rounded-full border border-emerald-200 text-[11px]">{{ $number }}</b>
                <span>{{ __($labelKey) }}</span>
            @endif
        </li>
    @endforeach
</ol>
