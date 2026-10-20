<x-global.layout
  :title="'DrinkFlow - ' . __('global.profile.page_title')"
  :user="$user"
  :active-tab="'profile'"
  :breadcrumbs="$breadcrumbs"
  :unread-notifications-count="$unreadNotificationsCount"
  :notifications="$notifications"
>
  <style>
    .profile-page-content .bg-white:has(#preferences-form),
    .profile-page-content .bg-white:has(#notifications-form),
    .profile-page-content .bg-white:has(#profile-shortcuts-title) { display: none; }
  </style>
  <div class="profile-page-content space-y-6">
    <!-- Success Status Alert -->
    @if(session('status'))
      <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200/80 text-emerald-900 text-sm font-medium flex items-center justify-between shadow-xs transition-all animate-fadeIn">
        <div class="flex items-center gap-2.5">
          <span class="w-7 h-7 rounded-xl bg-emerald-100 text-[#006948] flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-[18px]">check_circle</span>
          </span>
          <span>{{ session('status') }}</span>
        </div>
        <button type="button" onclick="this.parentElement.remove()" class="text-emerald-700 hover:text-emerald-950 p-1 rounded-lg hover:bg-emerald-100/60 transition-colors cursor-pointer" title="{{ __('global.common.close') }}">
          <span class="material-symbols-outlined text-[18px]">close</span>
        </button>
      </div>
    @endif

    <!-- Hero Header Trang -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-slate-200/80">
      <div class="space-y-1">
        <div class="flex flex-wrap items-center gap-3">
          <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight">{{ __('global.profile.title') }}</h1>
          <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 text-emerald-800 text-xs font-semibold border border-emerald-200/70">
            <span class="material-symbols-outlined text-[14px] text-[#006948]" style="font-variation-settings: 'FILL' 1;">verified</span>
            <span>{{ $workspaceText }}</span>
          </div>
        </div>
        <p class="text-sm text-slate-500 leading-relaxed max-w-3xl">
          {{ __('global.profile.subtitle') }}
        </p>
      </div>
      <div class="flex items-center gap-3 self-start md:self-auto shrink-0">
        <span class="text-xs text-slate-400">
          {{ __('global.profile.last_login', ['time' => $user->last_login_at ? $user->last_login_at->diffForHumans() : __('global.profile.just_now')]) }}
        </span>
        <button type="button" onclick="window.location.reload()" class="w-9 h-9 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 hover:text-slate-900 transition-colors flex items-center justify-center shadow-2xs cursor-pointer" title="{{ __('global.profile.refresh_data') }}">
          <span class="material-symbols-outlined text-[18px]">refresh</span>
        </button>
      </div>
    </div>

    <!-- Layout 2 Cột: 8 Cột Trái / 4 Cột Phải -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
      <!-- ==================== CỘT TRÁI (8 CỘT) ==================== -->
      <div class="lg:col-span-8 space-y-6">
        <!-- Card 1: Thông tin định danh & Doanh nghiệp SSO -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
          <div class="p-5 sm:p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/60">
            <div class="flex items-center gap-2.5">
              <span class="w-9 h-9 rounded-xl bg-emerald-50 text-[#006948] border border-emerald-100 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[20px]">badge</span>
              </span>
              <h2 class="text-base sm:text-lg font-bold text-slate-900">{{ __('global.profile.identity_card_title') }}</h2>
            </div>
          </div>

          <div class="p-6 space-y-6">
            <!-- Profile Identity Top Block -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-5 pb-6 border-b border-slate-100">
              <div class="relative shrink-0">
                <x-avatar :user="$user" size="2xl" class="border-2 border-[#006948]/30 ring-4 ring-emerald-500/10 shadow-sm" />
                <div class="absolute -top-2 -right-2 px-2 py-0.5 rounded-full bg-emerald-700 text-white font-mono text-[10px] uppercase font-bold tracking-wider shadow-2xs">
                  SSO
                </div>
              </div>

              <div class="flex-1 min-w-0 space-y-1.5">
                <div class="flex flex-wrap items-center gap-2.5">
                  <h3 class="text-xl font-bold text-slate-900 tracking-tight">{{ $user->name }}</h3>
                  @if($user->normalized_name && $user->normalized_name !== $user->name)
                    <span class="text-xs text-slate-500 font-medium">({{ $user->normalized_name }})</span>
                  @endif
                  <span class="px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-800 text-xs font-semibold border border-emerald-200/60">
                    {{ $role }}
                  </span>
                </div>

                <p class="text-xs sm:text-sm text-slate-600 flex flex-wrap items-center gap-1.5">
                  <span class="material-symbols-outlined text-[16px] text-[#006948]">mail</span>
                  <span class="font-medium text-slate-800">{{ $user->email }}</span>
                  <span class="text-xs text-slate-400 italic">{{ __('global.profile.sso_lock_notice') }}</span>
                  <span class="text-xs text-slate-400">{{ __('global.profile.managed_by_it') }}</span>
                </p>

                <p class="text-xs sm:text-sm text-slate-600 flex flex-wrap items-center gap-1.5" data-profile-phone>
                  <span class="material-symbols-outlined text-[16px] text-[#006948]">call</span>
                  @if($phone !== '')
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="font-medium text-slate-800 hover:text-[#006948]">{{ $phone }}</a>
                  @else
                    <span class="text-xs text-slate-400 italic">{{ __('global.profile.phone_empty') }}</span>
                  @endif
                </p>

                <div class="flex flex-wrap gap-3 text-xs text-slate-500 pt-1">
                  <span>{{ __('global.profile.department') }} <strong class="text-slate-800 font-medium">{{ $department }}</strong></span>
                  <span class="text-slate-300">•</span>
                  <span>{{ __('global.profile.employee_code') }} <strong class="text-slate-800 font-mono font-medium">{{ $userCode }}</strong></span>
                </div>
              </div>
            </div>

            <!-- About me (Markdown rendered server-side with raw HTML escaped) -->
            <div class="space-y-1.5 pb-6 border-b border-slate-100" data-profile-bio>
              <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('global.profile.bio_title') }}</h4>
              @if($bioHtml !== '')
                <div class="text-sm leading-relaxed text-slate-700 break-words [&_p]:mb-2 [&_p:last-child]:mb-0 [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:mb-2 [&_ol]:list-decimal [&_ol]:pl-5 [&_ol]:mb-2 [&_a]:text-[#006948] [&_a]:underline [&_strong]:font-semibold [&_strong]:text-slate-900">{!! $bioHtml !!}</div>
              @else
                <p class="text-sm italic text-slate-400">{{ __('global.profile.bio_empty') }}</p>
              @endif
            </div>

            <!-- Editable Contact Details Form -->
            <form action="{{ route('user.me.profile.update') }}" method="POST" class="space-y-4">
              @csrf
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Phone -->
                <div class="space-y-1.5">
                  <label for="contact-phone" class="h-5 text-xs font-semibold text-slate-700 flex items-center">
                    <span>{{ __('global.profile.phone_label') }}</span>
                  </label>
                  <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/4 text-slate-400 text-[18px] leading-none">call</span>
                    <input id="contact-phone" name="phone" type="text" value="{{ old('phone', $phone) }}" placeholder="{{ __('global.profile.phone_placeholder') }}" class="w-full h-10 pl-9 pr-3.5 bg-slate-50/70 border border-slate-200 rounded-xl text-sm font-medium text-slate-900 focus:bg-white focus:border-[#006948] focus:ring-1 focus:ring-[#006948] transition-all">
                  </div>
                </div>

                <!-- Desk / Floor Identifier -->
                <div class="space-y-1.5 relative">
                  <div class="h-5 flex items-center gap-1">
                    <label for="contact-desk" class="text-xs font-semibold text-slate-700">{{ __('global.profile.desk_label') }}</label>
                    <span class="group/info inline-flex">
                      <button type="button" class="inline-flex h-5 w-5 items-center justify-center rounded-full text-slate-400 hover:text-[#006948] focus:text-[#006948] focus:outline-none cursor-help" aria-label="{{ __('global.profile.desk_info') }}">
                        <span class="material-symbols-outlined text-[16px]" aria-hidden="true">info</span>
                      </button>
                      <span role="tooltip" class="pointer-events-none absolute bottom-full left-0 right-0 z-20 mb-1 rounded-lg bg-slate-900 px-3 py-2 text-[11px] font-medium leading-relaxed text-white opacity-0 shadow-lg transition-opacity group-hover/info:opacity-100 group-focus-within/info:opacity-100">{{ __('global.profile.desk_info') }}</span>
                    </span>
                  </div>
                  <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/4 text-slate-400 text-[18px] leading-none">groups</span>
                    <input id="contact-desk" name="desk_location" type="text" value="{{ old('desk_location', $deskLocation) }}" placeholder="{{ __('global.profile.desk_placeholder') }}" class="w-full h-10 pl-9 pr-3.5 bg-slate-50/70 border border-slate-200 rounded-xl text-sm font-medium text-slate-900 focus:bg-white focus:border-[#006948] focus:ring-1 focus:ring-[#006948] transition-all">
                  </div>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Delivery Location -->
                <div class="space-y-1.5">
                  <label for="contact-delivery" class="text-xs font-semibold text-slate-700">{{ __('global.profile.delivery_label') }}</label>
                  <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/4 text-slate-400 text-[18px] leading-none">apartment</span>
                    <input id="contact-delivery" name="delivery_location" type="text" value="{{ old('delivery_location', $deliveryLocation) }}" placeholder="{{ __('global.profile.delivery_placeholder') }}" class="w-full h-10 pl-9 pr-3.5 bg-slate-50/70 border border-slate-200 rounded-xl text-sm font-medium text-slate-900 focus:bg-white focus:border-[#006948] focus:ring-1 focus:ring-[#006948] transition-all">
                  </div>
                </div>

                <!-- Birthday: only the day and month are shown to the room (banner + celebration page) -->
                <div class="space-y-1.5">
                  <label for="contact-birthday" class="text-xs font-semibold text-slate-700">{{ __('global.profile.birthday_label') }}</label>
                  <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/4 text-slate-400 text-[18px] leading-none pointer-events-none">cake</span>
                    {{-- Target of the birthday reminder link (#contact-birthday): stops below the sticky header, label included. --}}
                    <input id="contact-birthday" name="birthday" type="date" value="{{ old('birthday', $birthday) }}" min="1900-01-01" max="{{ now()->subDay()->toDateString() }}" aria-describedby="contact-birthday-hint" style="scroll-margin-top: 10rem" class="w-full h-10 pl-9 pr-3.5 bg-slate-50/70 border border-slate-200 rounded-xl text-sm font-medium text-slate-900 focus:bg-white focus:border-[#006948] focus:ring-1 focus:ring-[#006948] transition-all">
                  </div>
                  <p id="contact-birthday-hint" class="text-xs text-slate-400">{{ __('global.profile.birthday_hint') }}</p>
                  @error('birthday')<p class="text-xs text-rose-600" role="alert">{{ $message }}</p>@enderror
                </div>
              </div>

              {{-- About me: Markdown editor (resources/js/global/profile-bio.js); the word limit is checked again by App\Rules\MaxWords. --}}
              @php
                $bioToolbar = [
                  'bold' => ['format_bold', __('global.profile.md_bold')],
                  'italic' => ['format_italic', __('global.profile.md_italic')],
                  'list' => ['format_list_bulleted', __('global.profile.md_list')],
                  'ordered_list' => ['format_list_numbered', __('global.profile.md_ordered_list')],
                  'link' => ['link', __('global.profile.md_link')],
                ];
              @endphp
              <div class="space-y-1.5" data-bio-editor data-max-words="{{ $bioMaxWords }}"
                data-i18n="{{ json_encode([
                  'counter' => __('global.profile.bio_counter', ['count' => ':count', 'max' => ':max']),
                  'md_bold_sample' => __('global.profile.md_bold_sample'),
                  'md_italic_sample' => __('global.profile.md_italic_sample'),
                  'md_list_sample' => __('global.profile.md_list_sample'),
                  'md_link_sample' => __('global.profile.md_link_sample'),
                ], JSON_UNESCAPED_UNICODE) }}">
                <label for="contact-bio" class="text-xs font-semibold text-slate-700">{{ __('global.profile.bio_label') }}</label>
                <div class="rounded-xl border border-slate-200 bg-slate-50/70 focus-within:bg-white focus-within:border-[#006948] focus-within:ring-1 focus-within:ring-[#006948] transition-all overflow-hidden">
                  <div class="flex flex-wrap items-center gap-0.5 border-b border-slate-200 px-1.5 py-1" role="toolbar" aria-label="{{ __('global.profile.bio_toolbar') }}">
                    @foreach($bioToolbar as $format => [$icon, $label])
                      <button type="button" data-md-format="{{ $format }}" title="{{ $label }}" aria-label="{{ $label }}"
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-500 hover:bg-slate-100 hover:text-[#006948] transition-colors cursor-pointer">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">{{ $icon }}</span>
                      </button>
                    @endforeach
                  </div>
                  <textarea id="contact-bio" name="bio" rows="5" aria-describedby="contact-bio-hint" class="block w-full p-3.5 bg-transparent border-0 text-sm text-slate-800 focus:outline-none focus:ring-0 resize-y leading-relaxed" placeholder="{{ __('global.profile.bio_placeholder') }}">{{ old('bio', $bio) }}</textarea>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                  <p id="contact-bio-hint" class="text-slate-400">{{ __('global.profile.bio_hint', ['max' => $bioMaxWords]) }}</p>
                  <span class="text-slate-500 tabular-nums" data-bio-counter aria-live="polite"></span>
                </div>
                @error('bio')<p class="text-xs text-rose-600" role="alert">{{ $message }}</p>@enderror
              </div>

              <div class="space-y-1.5">
                <label for="pref-note" class="text-xs font-semibold text-slate-800">{{ __('global.profile.notes_label') }}</label>
                <textarea id="pref-note" name="note" rows="3" class="w-full p-3.5 bg-slate-50/70 rounded-xl border border-slate-200 text-xs sm:text-sm text-slate-800 focus:bg-white focus:border-[#006948] focus:ring-1 focus:ring-[#006948] transition-all resize-none leading-relaxed" placeholder="{{ __('global.profile.notes_placeholder') }}">{{ old('note', $orderNote) }}</textarea>
                <p class="text-xs text-slate-400">{{ __('global.profile.notes_hint') }}</p>
              </div>

              <div class="pt-2 flex justify-end">
                <button type="submit" class="px-4 py-2.5 bg-[#006948] hover:bg-[#005137] disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs sm:text-sm font-semibold rounded-xl transition-colors shadow-xs flex items-center gap-1.5 cursor-pointer">
                  <span class="material-symbols-outlined text-[18px]">save</span>
                  <span>{{ __('global.profile.save_contact') }}</span>
                </button>
              </div>
            </form>
          </div>
        </div>



        <!-- Card 3: Ghi chú đặt món mặc định -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
          <div class="p-5 sm:p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/60">
            <div class="flex items-center gap-2.5">
              <span class="w-9 h-9 rounded-xl bg-emerald-50 text-[#006948] border border-emerald-100 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[20px]">sticky_note_2</span>
              </span>
              <h2 class="text-base sm:text-lg font-bold text-slate-900">{{ __('global.profile.notes_title') }}</h2>
            </div>
            <span class="text-xs text-slate-400 hidden sm:inline">{{ __('global.profile.notes_subtitle') }}</span>
          </div>

          <div class="p-6">
            <form action="{{ route('user.me.profile.update') }}" method="POST" id="preferences-form" class="space-y-4">
              @csrf

              <!-- Ghi chú mặc định cho quán -->
              <div class="space-y-1.5">
                <label for="pref-note" class="text-xs font-semibold text-slate-800">{{ __('global.profile.notes_label') }}</label>
                <textarea id="pref-note" name="note" rows="3" class="w-full p-3.5 bg-slate-50/70 rounded-xl border border-slate-200 text-xs sm:text-sm text-slate-800 focus:bg-white focus:border-[#006948] focus:ring-1 focus:ring-[#006948] transition-all resize-none leading-relaxed" placeholder="{{ __('global.profile.notes_placeholder') }}">{{ old('note', $orderNote) }}</textarea>
                <p class="text-xs text-slate-400">{{ __('global.profile.notes_hint') }}</p>
              </div>

              <div class="pt-2 flex justify-end">
                <button type="submit" class="px-4 py-2.5 bg-[#006948] hover:bg-[#005137] text-white text-xs sm:text-sm font-semibold rounded-xl transition-colors shadow-xs flex items-center gap-1.5 cursor-pointer">
                  <span class="material-symbols-outlined text-[18px]">save</span>
                  <span>{{ __('global.profile.save_notes') }}</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- ==================== CỘT PHẢI (4 CỘT) ==================== -->
      <div class="lg:col-span-4 flex flex-col gap-6">
        <!-- Card 4: Cài đặt thông báo -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
          <div class="p-5 border-b border-slate-100 flex items-center gap-2.5 bg-slate-50/60">
            <span class="w-8 h-8 rounded-xl bg-emerald-50 text-[#006948] border border-emerald-100 flex items-center justify-center shrink-0">
              <span class="material-symbols-outlined text-[18px]">notifications_active</span>
            </span>
            <h2 class="text-base font-bold text-slate-900">{{ __('global.profile.notif_channels_title') }}</h2>
          </div>

          <form action="{{ route('user.me.profile.update') }}" method="POST" id="notifications-form" class="p-5 space-y-4">
            @csrf
            <!-- Toggle 1: Campaign Alerts -->
            <div class="flex items-start justify-between gap-3 pb-3 border-b border-slate-100">
              <div class="space-y-0.5">
                <span class="text-xs font-bold text-slate-900 block">{{ __('global.profile.notif_campaign') }}</span>
                <span class="text-[11px] text-slate-500 block leading-relaxed">{{ __('global.profile.notif_campaign_desc') }}</span>
              </div>
              <label class="relative inline-flex items-center cursor-pointer shrink-0 mt-0.5">
                <input type="checkbox" name="notify_campaign" value="1" {{ $notifyCampaign ? 'checked' : '' }} onchange="this.form.submit()" class="sr-only peer">
                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#006948]"></div>
              </label>
            </div>

            <!-- Toggle 2: Sound Chime -->
            <div class="flex items-start justify-between gap-3">
              <div class="space-y-0.5">
                <span class="text-xs font-bold text-slate-900 block">{{ __('global.profile.notif_sound') }}</span>
                <span class="text-[11px] text-slate-500 block leading-relaxed">{{ __('global.profile.notif_sound_desc') }}</span>
              </div>
              <label class="relative inline-flex items-center cursor-pointer shrink-0 mt-0.5">
                <input type="checkbox" name="notify_sound" value="1" {{ $notifySound ? 'checked' : '' }} onchange="this.form.submit()" class="sr-only peer">
                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#006948]"></div>
              </label>
            </div>
          </form>
        </div>

        <!-- Card 5: Thống kê thành viên & Huy hiệu (Membership Badge) -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
          <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/60">
            <div class="flex items-center gap-2.5">
              <span class="w-8 h-8 rounded-xl bg-emerald-50 text-[#006948] border border-emerald-100 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[18px]">workspace_premium</span>
              </span>
              <h2 class="text-base font-bold text-slate-900">{{ __('global.profile.membership_stats_title') }}</h2>
            </div>
            <span class="px-2.5 py-0.5 rounded-full bg-emerald-50 text-[#006948] border border-emerald-200 text-xs font-bold uppercase tracking-wider">
              {{ $memberLevel }}
            </span>
          </div>

          <div class="p-5 space-y-4">
            <!-- Badge display -->
            <div class="p-4 rounded-2xl bg-gradient-to-br from-emerald-50/60 via-white to-slate-50/50 border border-slate-200/80 flex items-center gap-3.5">
              <div class="w-12 h-12 rounded-2xl bg-[#006948] text-white flex items-center justify-center shrink-0 shadow-xs">
                <span class="material-symbols-outlined text-[26px]" style="font-variation-settings: 'FILL' 1;">{{ $badgeIcon }}</span>
              </div>
              <div class="min-w-0">
                <span class="text-[10px] text-slate-400 block uppercase tracking-wider font-semibold">{{ __('global.profile.internal_title') }}</span>
                <span class="text-sm font-bold text-slate-900 block truncate">{{ $memberTitle }}</span>
                <span class="text-xs font-medium text-[#006948] block truncate">{{ $memberSubtitle }}</span>
              </div>
            </div>

            <!-- Stats Metric Grid (Real data) -->
            <div class="grid grid-cols-2 gap-3 pt-1">
              <div class="p-3.5 rounded-xl border border-slate-200/70 bg-slate-50/50">
                <span class="text-xs text-slate-500 block font-medium">{{ __('global.profile.total_cups_ordered') }}</span>
                <div class="flex items-baseline gap-1 mt-1">
                  <span class="text-2xl font-bold text-slate-900 tracking-tight">{{ $totalCups }}</span>
                  <span class="text-xs text-slate-400">{{ __('global.common.items_count') }}</span>
                </div>
              </div>
              <div class="p-3.5 rounded-xl border border-slate-200/70 bg-slate-50/50">
                <span class="text-xs text-slate-500 block font-medium">{{ __('global.profile.on_time_payment') }}</span>
                <div class="flex items-baseline gap-1 mt-1">
                  <span class="text-2xl font-bold text-[#006948] tracking-tight">{{ $paymentRate }}%</span>
                </div>
              </div>
            </div>

            <div class="pt-3 text-xs text-slate-500 flex items-center justify-between border-t border-slate-100">
              <span>{{ __('global.profile.joined_system') }}</span>
              <span class="font-medium text-slate-800">{{ __('global.profile.joined_date_val', ['date' => $joinedDate, 'duration' => $joinedDuration]) }}</span>
            </div>
          </div>
        </div>

        @php
          $hasRooms = $hasRooms ?? ($user ? $user->roomUsers()->where('status', 'active')->exists() : false);
        @endphp
        <!-- Card 5: Liên kết & Lối tắt nhanh -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 space-y-3">
          <h3 id="profile-shortcuts-title" class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ __('global.profile.shortcuts_title') }}</h3>
          <div class="space-y-2">
            <a href="{{ route('user.me.devices') }}" class="flex items-center justify-between p-3 rounded-xl border border-slate-200/80 hover:bg-slate-50 transition-colors group">
              <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-[19px] text-slate-400 group-hover:text-[#006948] transition-colors">devices</span>
                <span class="text-xs sm:text-sm font-medium text-slate-800">{{ __('global.profile.shortcut_devices') }}</span>
              </div>
              <span class="material-symbols-outlined text-[16px] text-slate-400 group-hover:translate-x-0.5 group-hover:text-[#006948] transition-transform">arrow_forward</span>
            </a>
            @if($hasRooms)
              <a href="{{ route('user.me.payments') }}" class="flex items-center justify-between p-3 rounded-xl border border-slate-200/80 hover:bg-slate-50 transition-colors group">
                <div class="flex items-center gap-2.5">
                  <span class="material-symbols-outlined text-[19px] text-slate-400 group-hover:text-[#006948] transition-colors">receipt_long</span>
                  <span class="text-xs sm:text-sm font-medium text-slate-800">{{ __('global.profile.shortcut_payments') }}</span>
                </div>
                <span class="material-symbols-outlined text-[16px] text-slate-400 group-hover:translate-x-0.5 group-hover:text-[#006948] transition-transform">arrow_forward</span>
              </a>
              <a href="{{ route('user.me.statistics') }}" class="flex items-center justify-between p-3 rounded-xl border border-slate-200/80 hover:bg-slate-50 transition-colors group">
                <div class="flex items-center gap-2.5">
                  <span class="material-symbols-outlined text-[19px] text-slate-400 group-hover:text-[#006948] transition-colors">query_stats</span>
                  <span class="text-xs sm:text-sm font-medium text-slate-800">{{ __('global.profile.shortcut_stats') }}</span>
                </div>
                <span class="material-symbols-outlined text-[16px] text-slate-400 group-hover:translate-x-0.5 group-hover:text-[#006948] transition-transform">arrow_forward</span>
              </a>
            @endif
          </div>
        </div>
      </div>
    </div>
  </div>
</x-global.layout>
