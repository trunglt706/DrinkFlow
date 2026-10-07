@props([
    'title' => null,
    'description' => null,
    'keywords' => null,
    'ogTitle' => null,
    'ogDescription' => null,
    'ogType' => 'website',
    'ogImage' => null,
    'canonicalUrl' => null,
    'robots' => 'index, follow',
    'jsonLd' => [],
    'activeTab' => 'about',
    'version' => null,
    'termsUrl' => null,
    'versionsUrl' => null,
    'contactUrl' => null,
    'googleAuthUrl' => null,
])

<!DOCTYPE html>
<html class="light h-full" lang="{{ $locale }}">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    <!-- Primary SEO / Metadata -->
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDescription }}"/>
    <meta name="keywords" content="{{ $pageKeywords }}"/>
    <meta name="author" content="DrinkFlow"/>
    <meta name="robots" content="{{ $robots }}"/>
    <link rel="canonical" href="{{ $currentUrl }}"/>

    <!-- Open Graph / Facebook / Zalo / LinkedIn -->
    <meta property="og:site_name" content="DrinkFlow"/>
    <meta property="og:type" content="{{ $ogType }}"/>
    <meta property="og:url" content="{{ $currentUrl }}"/>
    <meta property="og:title" content="{{ $pageOgTitle }}"/>
    <meta property="og:description" content="{{ $pageOgDescription }}"/>
    <meta property="og:image" content="{{ $pageOgImage }}"/>
    <meta property="og:image:alt" content="{{ $pageOgTitle }}"/>
    <meta property="og:locale" content="{{ $ogLocale }}"/>

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image"/>
    <meta name="twitter:url" content="{{ $currentUrl }}"/>
    <meta name="twitter:title" content="{{ $pageOgTitle }}"/>
    <meta name="twitter:description" content="{{ $pageOgDescription }}"/>
    <meta name="twitter:image" content="{{ $pageOgImage }}"/>

    @foreach($alternateUrls as $altCode => $altUrl)
        <link rel="alternate" hreflang="{{ $altCode }}" href="{{ $altUrl }}"/>
    @endforeach
    @if(!empty($alternateUrls))
        <link rel="alternate" hreflang="x-default" href="{{ $alternateUrls[\App\Constants\AppLocale::DEFAULT] }}"/>
    @endif
    @foreach($alternateUrls as $altCode => $altUrl)
        @if($altCode !== $locale)
            <meta property="og:locale:alternate" content="{{ match ($altCode) { 'vi' => 'vi_VN', 'ja' => 'ja_JP', default => 'en_US' } }}"/>
        @endif
    @endforeach
    <meta name="theme-color" content="#006948"/>
    <link rel="apple-touch-icon" href="{{ asset('images/drinkflow-logo.png') }}"/>

    <!-- Schema.org JSON-LD Structured Data for Rich Search Results -->
    @php
        $siteUrl = \App\Support\Helpers\LocaleUrl::url('/');
        $orgNode = [
            '@type' => 'Organization',
            '@id' => url('/').'#organization',
            'name' => 'DrinkFlow',
            'url' => url('/'),
            'logo' => asset('images/drinkflow-logo.png'),
        ];
        $siteNode = [
            '@type' => 'WebSite',
            '@id' => url('/').'#website',
            'name' => 'DrinkFlow',
            'url' => $siteUrl,
            'inLanguage' => $locale,
            'publisher' => ['@id' => url('/').'#organization'],
        ];
        $graph = [$orgNode, $siteNode];
        if ($basePath === '/' && !empty($alternateUrls)) {
            $graph[] = [
                '@type' => 'SoftwareApplication',
                'name' => 'DrinkFlow',
                'url' => $currentUrl,
                'applicationCategory' => 'BusinessApplication',
                'operatingSystem' => 'Web',
                'inLanguage' => $locale,
                'description' => $pageDescription,
                'image' => $pageOgImage,
                'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'VND'],
            ];
        } elseif (!empty($alternateUrls)) {
            $crumbName = match (true) {
                str_starts_with($basePath, '/terms') => __('public.header.terms'),
                str_starts_with($basePath, '/versions') => __('public.header.versions'),
                default => __('public.header.contact'),
            };
            $graph[] = ['@type' => 'WebPage', 'name' => $pageTitle, 'url' => $currentUrl, 'inLanguage' => $locale, 'isPartOf' => ['@id' => url('/').'#website']];
            $graph[] = [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => __('public.header.about'), 'item' => $siteUrl],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => $crumbName, 'item' => $currentUrl],
                ],
            ];
        }
        foreach ($jsonLd as $extraNode) {
            $graph[] = $extraNode;
        }
        $structuredData = ['@context' => 'https://schema.org', '@graph' => $graph];
    @endphp
    <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin=""/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet"/>
    {{-- Only the icons used by pages on this layout (public pages, error pages, their components and public JS)
         are requested: ~14 KB instead of the ~1.1 MB full font. Add the name here (alphabetical order is required
         by Google Fonts) when a new icon appears on a public page, otherwise it renders as its ligature text. --}}
    @php
        $publicIconNames = [
            'account_balance_wallet', 'account_circle', 'arrow_back', 'arrow_drop_down', 'arrow_forward', 'arrow_right_alt',
            'arrow_upward', 'assignment_turned_in', 'auto_awesome', 'autorenew', 'block', 'bolt', 'bug_report', 'build',
            'calculate', 'calendar_today', 'call', 'campaign', 'cancel', 'chat', 'check', 'check_circle', 'checklist',
            'chevron_left', 'chevron_right', 'close', 'cloud_off', 'contact_support', 'content_copy', 'currency_exchange',
            'dashboard', 'domain', 'done_all', 'download', 'error', 'expand_less', 'expand_more', 'fact_check', 'first_page',
            'flag', 'format_list_bulleted', 'gavel', 'gpp_maybe', 'group', 'groups', 'headset_mic', 'help_outline', 'history',
            'home', 'info', 'key', 'lightbulb', 'link', 'local_cafe', 'local_shipping', 'lock', 'login', 'mail',
            'meeting_room', 'menu', 'menu_book', 'notes', 'payments', 'person', 'play_circle', 'point_of_sale',
            'progress_activity', 'qr_code_2', 'qr_code_scanner', 'quiz', 'receipt_long', 'refresh', 'report_problem',
            'schedule', 'search', 'send', 'shield', 'star', 'sticky_note_2', 'storefront', 'support_agent', 'switch_account',
            'sync_problem', 'timer', 'touch_app', 'verified_user', 'volunteer_activism', 'vpn_key', 'warning',
            'workspace_premium',
        ];
        sort($publicIconNames);
    @endphp
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0..1,0&amp;icon_names={{ implode(',', $publicIconNames) }}&amp;display=block" rel="stylesheet"/>

    {{-- The Socket.IO client (window.io) is injected by public.js after the page loads: public pages only listen
         for maintenance notices, so it must not delay first paint. --}}
    @if (file_exists(public_path('build/manifest.json')) || app()->isLocal())
        @vite(['resources/css/public.css', 'resources/js/public.js'])
    @endif

    @if(isset($head))
        {{ $head }}
    @endif
    <x-devtools-guard />
</head>

<body data-submit-loading-text="{{ __('global.common.loading') }}" data-realtime-url="{{ rtrim(config('services.realtime.public_url', 'http://localhost:3001'), '/') }}" @if (app()->isLocal()) data-socket-debug @endif class="bg-[#f8f9ff] text-[#0b1c30] min-h-full flex flex-col font-sans antialiased selection:bg-[#006948] selection:text-white">

    <!-- SHARED TOP NAVBAR -->
    <x-public.header
        :activeTab="$activeTab"
        :version="$version"
        :termsUrl="$termsUrl"
        :versionsUrl="$versionsUrl"
        :contactUrl="$contactUrl"
        :googleAuthUrl="$googleAuthUrl"
    />

    <!-- MAIN PAGE CONTENT -->
    {{ $slot }}

    <!-- SHARED FOOTER -->
    <x-public.footer
        :activeTab="$activeTab"
        :version="$version"
        :termsUrl="$termsUrl"
        :versionsUrl="$versionsUrl"
        :contactUrl="$contactUrl"
    />

    <!-- AUTH MODAL -->
    <x-public.auth-modal
        :googleAuthUrl="$googleAuthUrl"
        :termsUrl="$termsUrl"
    />

    <!-- FLOATING ACTIONS: CONTACT & GO TO TOP BUTTON -->
    <x-public.go-to-top />

    <!-- PUBLIC PAGE LOADING & SUBMIT OVERLAY -->
    <x-public.loading />

    @if(isset($scripts))
        {{ $scripts }}
    @endif
</body>
</html>
