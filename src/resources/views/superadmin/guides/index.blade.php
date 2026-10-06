@extends('superadmin.layout', ['title' => __('superadmin.guides.title'), 'active' => 'guides'])
@section('content')
    <x-superadmin.page-header :eyebrow="__('superadmin.layout.console')" :title="__('superadmin.guides.title')" :description="__('superadmin.guides.description')" />

    <section class="sa-card sa-section">
        @if ($articles === [])
            <x-superadmin.empty-state icon="menu_book" :title="__('superadmin.guides.empty')" />
        @else
            <ol class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3" data-guide-list>
                @foreach ($articles as $article)
                    <li>
                        <a href="{{ $article['url'] }}" class="flex h-full gap-3 rounded-xl border border-outline-variant p-4 hover:border-primary/60 hover:bg-surface-container-low transition-colors">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-primary/10 text-sm font-bold text-primary">{{ $article['number'] }}</span>
                            <span class="min-w-0">
                                <strong class="block text-[14px] text-on-surface">{{ $article['title'] }}</strong>
                                @if ($article['excerpt'] !== '')
                                    <small class="mt-1 block text-[12px] leading-relaxed text-outline line-clamp-3">{{ $article['excerpt'] }}</small>
                                @endif
                            </span>
                        </a>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>
@endsection
