{{-- Hands a superadmin page module (resources/js/superadmin/pages/*.js) its translations and URLs.
     The module registered under `name` in pages/index.js reads them from data-i18n / data-urls. --}}
@props([
    'name',
    'i18n' => [],
    'urls' => [],
])

<div hidden data-sa-page="{{ $name }}"
    data-i18n="{{ json_encode($i18n, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
    data-urls="{{ json_encode($urls, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) }}" {{ $attributes }}></div>
