@props([
    'version' => null,
    'googleAuthUrl' => null,
])

<!-- HERO SECTION -->
<section id="about" class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center pt-4 pb-6">
    <div class="lg:col-span-7 flex flex-col items-start gap-5">
        <!-- Pill Badge -->
        <div
            class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#eff4ff] border border-[#bccac0]/40 text-[#0b1c30] text-xs font-medium">
            <span class="flex h-2 w-2 rounded-full bg-[#059669] animate-pulse"></span>
            <span>{{ __('public.hero.badge', ['version' => $version]) }}</span>
        </div>

        <!-- Headline -->
        <h1
            class="text-3xl sm:text-4xl lg:text-[40px] leading-tight sm:leading-[48px] font-bold text-[#0F172A] tracking-tight">
            {{ __('public.hero.title') }}
        </h1>

        <!-- Subtitle -->
        <p class="text-base sm:text-lg text-[#475569] max-w-xl leading-relaxed">
            {{ __('public.hero.subtitle') }}
        </p>

        <!-- CTA Buttons -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 pt-2 w-full sm:w-auto">
            @auth('web')
                <a href="{{ route('user.me.dashboard') }}"
                    class="w-full sm:w-auto justify-center bg-[#059669] hover:bg-[#047857] text-white text-sm font-semibold px-5 py-2.5 rounded-[6px] transition-all active:scale-[0.98] duration-100 shadow-sm flex items-center gap-2 cursor-pointer">
                    <span>{{ __('public.hero.cta_primary') }}</span>
                    <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                </a>
            @else
                <button type="button"
                    class="btn-google-sso w-full sm:w-auto justify-center bg-[#059669] hover:bg-[#047857] text-white text-sm font-semibold px-5 py-2.5 rounded-[6px] transition-all active:scale-[0.98] duration-100 shadow-sm flex items-center gap-2 cursor-pointer">
                    <span>{{ __('public.hero.cta_primary') }}</span>
                    <span class="material-symbols-outlined text-[18px]">login</span>
                </button>
            @endauth
            <button type="button"
                class="open-video-btn w-full sm:w-auto justify-center bg-white border border-[#E2E8F0] hover:bg-[#F8FAFC] hover:border-[#CBD5E1] text-[#0F172A] text-sm font-medium px-5 py-2.5 rounded-[6px] transition-colors flex items-center gap-2 shadow-2xs cursor-pointer">
                <span class="material-symbols-outlined text-[18px] text-[#006948]">play_circle</span>
                <span>{{ __('public.hero.cta_video') }}</span>
            </button>
        </div>

        <!-- Enterprise Trust Indicators -->
        <div class="flex flex-wrap items-center gap-6 pt-4 text-[#545c72] text-xs">
            <div class="flex items-center gap-1.5 font-medium">
                <span class="material-symbols-outlined text-[16px] text-[#006948]">verified_user</span>
                <span>{{ __('public.hero.trust_oauth') }}</span>
            </div>
            <div class="flex items-center gap-1.5 font-medium">
                <span class="material-symbols-outlined text-[16px] text-[#006948]">qr_code_2</span>
                <span>{{ __('public.hero.trust_vietqr') }}</span>
            </div>
            <div class="flex items-center gap-1.5 font-medium">
                <span class="material-symbols-outlined text-[16px] text-[#006948]">bolt</span>
                <span>{{ __('public.hero.trust_socket') }}</span>
            </div>
        </div>
    </div>

    <!-- Mock Order Session Card (Level 1 Surface) -->
    {{-- The only image above the fold is the landing LCP element: load it eagerly with high priority, never lazily. --}}
    <div
        class="lg:col-span-5 rounded-2xl overflow-hidden border border-[#E2E8F0] shadow-sm bg-white relative aspect-[4/3] flex items-center justify-center group">
        <img alt="{{ __('public.hero.image_alt') }}"
            class="w-full h-full object-cover object-center transform group-hover:scale-105 transition-transform duration-500"
            src="{{ asset('images/home-intro.webp') }}" width="512" height="382" fetchpriority="high" loading="eager"
            decoding="async"
            onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'600\' height=\'450\' viewBox=\'0 0 600 450\'><rect width=\'600\' height=\'450\' fill=\'%23eff4ff\'/><text x=\'50%25\' y=\'50%25\' dominant-baseline=\'middle\' text-anchor=\'middle\' font-family=\'sans-serif\' font-size=\'20\' fill=\'%23006948\'>DrinkFlow Workspace Preview</text></svg>';" />
    </div>
</section>
