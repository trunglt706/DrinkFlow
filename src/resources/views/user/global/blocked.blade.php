<!DOCTYPE html>
<html class="h-full" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>{{ __('global.blocked.page_title') }}</title>
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=block" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <script id="tailwind-config">
    tailwind.config = {
      darkMode: "class",
      theme: {
        extend: {
          colors: {
            "primary": "#006948",
            "primary-container": "#00855d",
            "error": "#ba1a1a",
            "error-container": "#ffdad6",
          },
          fontFamily: {
            sans: ["Inter", "-apple-system", "BlinkMacSystemFont", "Segoe UI", "Roboto", "sans-serif"]
          }
        }
      }
    };
  </script>
  @if (file_exists(public_path('build/manifest.json')) || app()->isLocal())
    @vite(['resources/css/global.css', 'resources/js/global.js'])
  @endif
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
    <x-devtools-guard />
</head>
<body 
  x-data="{
    showAppealModal: false,
    showContactModal: false,
  }" 
  class="bg-[#f8f9ff] text-slate-800 font-sans min-h-screen flex flex-col justify-between"
>
  <!-- TOP APP BAR -->
  <header class="w-full bg-white border-b border-slate-200 sticky top-0 z-30 shadow-2xs">
    <div class="w-full max-w-[1200px] mx-auto px-6 h-16 flex items-center justify-between">
      <!-- Left: Logo & Enterprise Compliance Badge -->
      <div class="flex items-center gap-3">
        <a class="text-xl font-bold text-[#006948] tracking-tight flex items-center gap-2" href="{{ url('/') }}">
          <span class="w-8 h-8 rounded-xl flex items-center justify-center text-white shadow-2xs shrink-0"
                style="background: linear-gradient(135deg, #006948 0%, #047857 100%); background-color: #006948; border: 1px solid #005137;">
            <svg class="w-4.5 h-4.5 text-white fill-current" viewBox="0 0 24 24" aria-hidden="true" style="width: 18px; height: 18px; fill: #ffffff; color: #ffffff;">
              <path d="M20 3H4v10c0 2.21 1.79 4 4 4h6c2.21 0 4-1.79 4-4v-3h2c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 5h-2V5h2v3zM4 19h16v2H4z"/>
            </svg>
          </span>
          DrinkFlow
        </a>
        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs bg-rose-50 text-rose-700 border border-rose-200 font-medium">
          <span class="material-symbols-outlined text-[14px] text-rose-600">gshield</span>
          {{ __('global.blocked.security_compliance') }}
        </span>
      </div>

      <!-- Right: User Context, Language Switcher & Actions -->
      <div class="flex items-center gap-3">
        <div class="hidden sm:flex items-center gap-3 pr-3 border-r border-slate-200">
          <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-xs text-[#006948] font-bold border border-slate-200">
            {{ strtoupper(mb_substr($user->name ?: 'U', 0, 2)) }}
          </div>
          <div class="flex flex-col text-left">
            <span class="text-xs font-semibold text-slate-800 flex items-center gap-1.5">
              {{ $user->name }}
              <span class="inline-block w-2 h-2 rounded-full bg-rose-500" title="{{ __('global.blocked.suspended_tooltip') }}"></span>
            </span>
            <span class="text-[11px] text-slate-500">{{ $user->email }}</span>
          </div>
        </div>

        @php
          $currentLocale = app()->getLocale();
          $activeLocaleMeta = $locales[$currentLocale] ?? $locales['vi'];
        @endphp
        <!-- Language Selector Dropdown -->
        <div class="relative" x-data="{ openLang: false }">
          <button type="button"
                  @click="openLang = !openLang"
                  @click.outside="openLang = false"
                  class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl text-xs font-medium text-slate-600 hover:bg-slate-50 transition-colors border border-slate-200 shadow-2xs cursor-pointer"
                  title="{{ __('global.header.language_select') }}">
            <span>{{ $activeLocaleMeta['flag'] }}</span>
            <span class="font-semibold text-slate-800">{{ $activeLocaleMeta['code'] }}</span>
            <span class="material-symbols-outlined text-[16px] text-slate-400">arrow_drop_down</span>
          </button>

          <div x-show="openLang"
               x-cloak
               class="absolute right-0 top-12 w-36 bg-white rounded-xl shadow-xl border border-slate-200/80 py-1.5 z-50">
            @foreach($locales as $code => $meta)
              <a href="{{ route('locale.switch', $code) }}"
                 class="flex items-center justify-between px-3 py-2 text-xs text-slate-700 hover:bg-emerald-50 hover:text-[#006948] transition-colors {{ $currentLocale === $code ? 'font-semibold text-[#006948] bg-emerald-50/50' : '' }}">
                <div class="flex items-center gap-2">
                  <span>{{ $meta['flag'] }}</span>
                  <span>{{ $meta['name'] }}</span>
                </div>
                @if($currentLocale === $code)
                  <span class="material-symbols-outlined text-[16px] text-[#006948]">check</span>
                @endif
              </a>
            @endforeach
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button 
            type="button" 
            @click="showContactModal = true" 
            class="p-2 rounded-xl text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition-colors cursor-pointer" 
            title="{{ __('global.header.help_support') }}"
          >
            <span class="material-symbols-outlined text-[20px]">help_outline</span>
          </button>
          
          <form action="{{ route('logout') }}" method="POST" class="inline">
            @csrf
            <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-xs font-medium text-slate-700 transition-colors shadow-2xs cursor-pointer">
              <span class="material-symbols-outlined text-[16px] text-slate-400">logout</span>
              <span>{{ __('global.header.logout') }}</span>
            </button>
          </form>
        </div>
      </div>
    </div>
  </header>

  <!-- MAIN WRAPPER -->
  <main class="flex-1 w-full max-w-[1200px] mx-auto px-6 py-8 flex flex-col items-center">
    <!-- Status Flash Notification -->
    @if(session('status'))
      <div class="w-full mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between shadow-2xs">
        <div class="flex items-center gap-2">
          <span class="material-symbols-outlined text-emerald-600 text-[20px]">check_circle</span>
          <span>{{ session('status') }}</span>
        </div>
        <button @click="$el.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700">
          <span class="material-symbols-outlined text-[18px]">close</span>
        </button>
      </div>
    @endif

    <!-- Top System Alert Notification Banner -->
    <div class="w-full mb-6 bg-rose-50 border border-rose-200 rounded-xl p-3 sm:px-4 flex items-center justify-between gap-3 text-rose-800">
      <div class="flex items-center gap-2.5 text-xs sm:text-sm">
        <span class="material-symbols-outlined text-rose-600 text-xl shrink-0">warning</span>
        <span>{{ __('global.blocked.suspended_banner') }}</span>
      </div>
      <span class="hidden md:inline-block px-2.5 py-0.5 rounded-md bg-white text-xs font-mono font-semibold text-rose-600 border border-rose-200 whitespace-nowrap">
        {{ __('global.blocked.incident_code', ['code' => $incidentCode]) }}
      </span>
    </div>

    <!-- Central Structured Bento Grid -->
    <div class="w-full grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
      <!-- Primary Core Status & Details (8 Columns) -->
      <section class="lg:col-span-8 flex flex-col gap-6">
        <!-- Main Alert Box Card -->
        <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-2xs">
          <div class="flex flex-col sm:flex-row items-start gap-4">
            <div class="w-12 h-12 rounded-xl bg-rose-50 border border-rose-200 flex items-center justify-center shrink-0">
              <span class="material-symbols-outlined text-rose-600 text-2xl">lock</span>
            </div>
            <div class="flex-1">
              <h1 class="text-xl font-bold text-slate-900 tracking-tight">{{ __('global.blocked.title') }}</h1>
              <p class="text-sm text-slate-600 leading-relaxed mt-1">
                {{ __('global.blocked.description', ['email' => $user->email]) }}
              </p>
            </div>
          </div>

          <!-- Incident Parameter Inspection Box -->
          <div class="mt-6 border-t border-slate-100 pt-5">
            <div class="flex items-center justify-between mb-3">
              <h2 class="text-xs font-semibold text-slate-700 flex items-center gap-1.5">
                <span class="material-symbols-outlined text-sm text-[#006948]">receipt_long</span>
                {{ __('global.blocked.incident_info_title') }}
              </h2>
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                {{ __('global.blocked.status_suspended') }}
              </span>
            </div>

            <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
              <div>
                <span class="text-[11px] text-slate-400 block uppercase tracking-wide">{{ __('global.blocked.ref_code') }}</span>
                <span class="text-slate-800 font-semibold font-mono text-sm mt-0.5 block">{{ $incidentCode }}</span>
              </div>
              <div>
                <span class="text-[11px] text-slate-400 block uppercase tracking-wide">{{ __('global.blocked.recorded_at') }}</span>
                <span class="text-slate-700 font-medium mt-0.5 block">{{ $recordedAt }}</span>
              </div>
              <div class="sm:col-span-2">
                <span class="text-[11px] text-slate-400 block uppercase tracking-wide">{{ __('global.blocked.suspension_reason') }}</span>
                <p class="text-slate-700 font-medium mt-0.5 leading-relaxed">
                  {{ __('global.blocked.default_reason') }}
                </p>
              </div>
              <div>
                <span class="text-[11px] text-slate-400 block uppercase tracking-wide">{{ __('global.blocked.managing_dept') }}</span>
                <span class="text-slate-700 font-medium mt-0.5 block">{{ __('global.blocked.dept_name') }}</span>
              </div>
              <div>
                <span class="text-[11px] text-slate-400 block uppercase tracking-wide">{{ __('global.blocked.violation_level') }}</span>
                <span class="text-rose-600 font-semibold mt-0.5 flex items-center gap-1.5">
                  <span class="w-2 h-2 rounded-full bg-rose-500 inline-block"></span>
                  {{ __('global.blocked.level_2_desc') }}
                </span>
              </div>
            </div>
          </div>

          <!-- Scope of Limitation Warning -->
          <div class="mt-5 p-3.5 bg-slate-50 rounded-xl border border-slate-200 flex items-start gap-2.5 text-xs text-slate-600">
            <span class="material-symbols-outlined text-slate-400 text-lg mt-0.5 shrink-0">policy</span>
            <div>
              <span class="font-semibold text-slate-800">{{ __('global.blocked.scope_title') }}</span> {{ __('global.blocked.scope_desc') }}
            </div>
          </div>
        </div>

        <!-- Next Steps Protocol Card -->
        <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-2xs">
          <h2 class="text-base font-bold text-slate-900 mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-[#006948] text-xl">checklist</span>
            {{ __('global.blocked.protocol_title') }}
          </h2>

          <ol class="space-y-4 relative before:absolute before:left-3.5 before:top-2 before:bottom-2 before:w-[1px] before:bg-slate-200">
            <!-- Step 1 -->
            <li class="relative flex items-start gap-3 pl-8">
              <span class="absolute left-1.5 top-0.5 w-5 h-5 rounded-full bg-white border-2 border-[#006948] text-[#006948] text-xs font-bold flex items-center justify-center -translate-x-1/2">
                1
              </span>
              <div class="flex-1">
                <h3 class="text-xs font-semibold text-slate-800">{{ __('global.blocked.step_1_title') }}</h3>
                <p class="text-xs text-slate-500 mt-0.5">
                  {{ __('global.blocked.step_1_desc', ['debt' => \App\Support\Helpers\FormatHelper::formatCurrency($totalDebt)]) }}
                </p>
              </div>
            </li>

            <!-- Step 2 -->
            <li class="relative flex items-start gap-3 pl-8">
              <span class="absolute left-1.5 top-0.5 w-5 h-5 rounded-full bg-white border-2 border-[#006948] text-[#006948] text-xs font-bold flex items-center justify-center -translate-x-1/2">
                2
              </span>
              <div class="flex-1">
                <h3 class="text-xs font-semibold text-slate-800">{{ __('global.blocked.step_2_title') }}</h3>
                <p class="text-xs text-slate-500 mt-0.5">
                  {{ __('global.blocked.step_2_desc') }}
                </p>
              </div>
            </li>

            <!-- Step 3 -->
            <li class="relative flex items-start gap-3 pl-8">
              <span class="absolute left-1.5 top-0.5 w-5 h-5 rounded-full bg-white border-2 border-[#006948] text-[#006948] text-xs font-bold flex items-center justify-center -translate-x-1/2">
                3
              </span>
              <div class="flex-1">
                <h3 class="text-xs font-semibold text-slate-800">{{ __('global.blocked.step_3_title') }}</h3>
                <p class="text-xs text-slate-500 mt-0.5">
                  {{ __('global.blocked.step_3_desc') }}
                </p>
              </div>
            </li>
          </ol>

          <!-- Primary Actions Cluster -->
          <div class="mt-6 pt-5 border-t border-slate-100 flex flex-wrap items-center gap-3">
            <button 
              type="button" 
              @click="showAppealModal = true"
              class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#006948] hover:bg-[#005137] text-white text-xs font-semibold rounded-xl shadow-xs transition-colors cursor-pointer"
            >
              <span class="material-symbols-outlined text-[17px]">send</span>
              <span>{{ __('global.blocked.btn_send_appeal') }}</span>
            </button>
            <button 
              type="button" 
              @click="showContactModal = true"
              class="inline-flex items-center gap-2 px-4 py-2.5 bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 text-xs font-medium rounded-xl transition-colors cursor-pointer shadow-2xs"
            >
              <span class="material-symbols-outlined text-[17px] text-slate-400">contact_support</span>
              <span>{{ __('global.blocked.btn_contact_admin') }}</span>
            </button>
          </div>
        </div>
      </section>

      <!-- Sidebar / Quick Support Panel (4 Columns) -->
      <aside class="lg:col-span-4 flex flex-col gap-6">
        <!-- Summary Settlement Checklist Card -->
        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-2xs">
          <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">{{ __('global.blocked.pending_settlement') }}</h3>
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">{{ __('global.blocked.items_count', ['count' => count($pendingDebts)]) }}</span>
          </div>

          <div class="divide-y divide-slate-100 text-xs">
            @forelse($pendingDebts as $item)
              <div class="py-3 flex items-start justify-between gap-2">
                <div class="min-w-0 flex-1">
                  <p class="font-medium text-slate-800 truncate">{{ $item['title'] }}</p>
                  <p class="text-[11px] text-slate-400 mt-0.5 truncate">{{ $item['subtitle'] }}</p>
                </div>
                <span class="font-semibold text-rose-600 text-right tabular-nums whitespace-nowrap">{{ \App\Support\Helpers\FormatHelper::formatCurrency($item['amount']) }}</span>
              </div>
            @empty
              <div class="py-6 text-center text-slate-400">
                <span class="material-symbols-outlined text-[24px] text-slate-300 block mb-1">check_circle</span>
                {{ __('global.blocked.no_pending_debts') }}
              </div>
            @endforelse
          </div>

          <div class="mt-2 pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-semibold">
            <span class="text-slate-800">{{ __('global.blocked.total_pending') }}</span>
            <span class="text-rose-600 font-mono text-sm">{{ \App\Support\Helpers\FormatHelper::formatCurrency($totalDebt) }}</span>
          </div>
          <p class="mt-3 text-[11px] text-slate-500 leading-relaxed">
            {{ __('global.blocked.auto_unlock_note', ['status' => __('global.blocked.status_active')]) }}
          </p>
        </div>

        <!-- Direct Enterprise Support Desk -->
        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-2xs">
          <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
            <span class="material-symbols-outlined text-[#006948] text-base">support_agent</span>
            {{ __('global.blocked.internal_support_channels') }}
          </h3>
          <ul class="space-y-3 text-xs">
            <li class="flex items-start gap-2.5">
              <span class="material-symbols-outlined text-slate-400 text-[18px] mt-0.5">mail</span>
              <div>
                <span class="text-[11px] text-slate-400 block">{{ __('global.blocked.it_email_label') }}</span>
                <a class="text-[#006948] hover:underline font-medium" href="mailto:it-security@company.com">it-security@company.com</a>
              </div>
            </li>
            <li class="flex items-start gap-2.5">
              <span class="material-symbols-outlined text-slate-400 text-[18px] mt-0.5">phone_in_talk</span>
              <div>
                <span class="text-[11px] text-slate-400 block">{{ __('global.blocked.hotline_label') }}</span>
                <a class="font-medium text-[#006948] hover:underline" href="tel:0377300950">{{ __('global.blocked.hotline_val') }}</a>
              </div>
            </li>
          </ul>
        </div>
      </aside>
    </div>
  </main>

  <!-- FOOTER -->
  <footer class="bg-white border-t border-slate-200 py-4 mt-auto">
    <div class="w-full max-w-[1200px] mx-auto px-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
      <span>{{ __('global.blocked.footer_desc') }}</span>
      <div class="flex items-center gap-4">
        <a href="{{ route('terms') }}" class="hover:text-slate-800">{{ __('global.blocked.footer_terms') }}</a>
        <span>•</span>
        <a href="{{ route('contact') }}" class="hover:text-slate-800">{{ __('global.blocked.footer_support') }}</a>
      </div>
    </div>
  </footer>

  <!-- MODAL: Gửi yêu cầu mở khóa / Khiếu nại -->
  <div 
    x-show="showAppealModal"
    x-cloak
    style="display: none;"
    :class="{ 'flex': showAppealModal, 'hidden': !showAppealModal }"
    class="fixed inset-0 z-50 items-center justify-center bg-black/40 backdrop-blur-sm p-4"
    @click.self="showAppealModal = false"
    @keydown.escape.window="showAppealModal = false"
  >
    <div class="bg-white border border-slate-200 rounded-2xl max-w-md w-full p-6 shadow-xl relative">
      <button @click="showAppealModal = false" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 p-1 rounded-lg hover:bg-slate-100 transition-colors" aria-label="{{ __('global.common.close') }}">
        <span class="material-symbols-outlined text-xl">close</span>
      </button>

      <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 rounded-full bg-emerald-50 text-[#006948] flex items-center justify-center shrink-0 border border-emerald-100">
          <span class="material-symbols-outlined text-xl">send</span>
        </div>
        <div>
          <h3 class="text-base font-bold text-slate-900">{{ __('global.blocked.modal_appeal_title') }}</h3>
          <p class="text-xs text-slate-500">{{ __('global.blocked.modal_appeal_incident', ['code' => $incidentCode]) }}</p>
        </div>
      </div>

      <form action="{{ route('user.blocked.appeal') }}" method="POST" class="space-y-4">
        @csrf
        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1" for="reason">
            {{ __('global.blocked.modal_reason_label') }}
          </label>
          <textarea 
            id="reason" 
            name="reason" 
            rows="3" 
            class="w-full p-3 text-xs rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#006948]"
            placeholder="{{ __('global.blocked.modal_reason_placeholder') }}"
          ></textarea>
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1" for="attachment_note">
            {{ __('global.blocked.modal_ref_label') }}
          </label>
          <input 
            type="text" 
            id="attachment_note" 
            name="attachment_note" 
            class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#006948]"
            placeholder="{{ __('global.blocked.modal_ref_placeholder') }}"
          >
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <button type="button" @click="showAppealModal = false" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 text-xs font-medium transition-colors">
            {{ __('global.common.cancel') }}
          </button>
          <button type="submit" class="px-4 py-2 rounded-xl bg-[#006948] text-white hover:bg-[#005137] text-xs font-semibold transition-colors flex items-center gap-1.5 shadow-xs">
            <span class="material-symbols-outlined text-[16px]">send</span> {{ __('global.blocked.modal_send_btn') }}
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- MODAL: Thông tin liên hệ Admin -->
  <div 
    x-show="showContactModal"
    x-cloak
    style="display: none;"
    :class="{ 'flex': showContactModal, 'hidden': !showContactModal }"
    class="fixed inset-0 z-50 items-center justify-center bg-black/40 backdrop-blur-sm p-4"
    @click.self="showContactModal = false"
    @keydown.escape.window="showContactModal = false"
  >
    <div class="bg-white border border-slate-200 rounded-2xl max-w-md w-full p-6 shadow-xl relative">
      <button @click="showContactModal = false" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 p-1 rounded-lg hover:bg-slate-100 transition-colors" aria-label="{{ __('global.common.close') }}">
        <span class="material-symbols-outlined text-xl">close</span>
      </button>

      <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 rounded-full bg-slate-100 text-slate-700 flex items-center justify-center shrink-0">
          <span class="material-symbols-outlined text-xl">support_agent</span>
        </div>
        <div>
          <h3 class="text-base font-bold text-slate-900">{{ __('global.blocked.modal_contact_title') }}</h3>
          <p class="text-xs text-slate-500">{{ __('global.blocked.modal_contact_subtitle') }}</p>
        </div>
      </div>

      <div class="space-y-3 mb-6 text-xs text-slate-600">
        <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
          <p class="font-semibold text-slate-800">{{ __('global.blocked.modal_contact_it_title') }}</p>
          <p class="mt-0.5 text-slate-500">{{ __('global.blocked.modal_contact_it_email', ['email' => 'it-security@company.com']) }}</p>
          <p class="text-[11px] text-slate-400">{{ __('global.blocked.modal_contact_it_desc') }}</p>
        </div>

        <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
          <p class="font-semibold text-slate-800">{{ __('global.blocked.modal_contact_acc_title') }}</p>
          <a class="mt-0.5 inline-block text-slate-500 hover:text-[#006948] hover:underline" href="tel:0377300950">{{ __('global.blocked.modal_contact_acc_hotline', ['phone' => '0377300950']) }}</a>
          <p class="text-[11px] text-slate-400">{{ __('global.blocked.modal_contact_acc_desc') }}</p>
        </div>
      </div>

      <div class="flex justify-end">
        <button type="button" @click="showContactModal = false" class="px-4 py-2 rounded-xl bg-[#006948] text-white hover:bg-[#005137] text-xs font-semibold transition-colors">
          {{ __('global.blocked.modal_contact_close') }}
        </button>
      </div>
    </div>
  </div>
</body>
</html>
