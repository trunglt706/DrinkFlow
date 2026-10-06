@extends('superadmin.layout', ['title' => $article['title'], 'active' => 'guides'])
@section('content')
    <x-superadmin.page-header :back="route('superadmin.guides.index')" :back-label="__('superadmin.guides.title')" :title="$article['title']" />

    <div class="grid grid-cols-1 xl:grid-cols-[260px_1fr] gap-5 items-start">
        <nav class="sa-card sa-section xl:sticky xl:top-20" aria-label="{{ __('superadmin.guides.contents') }}">
            <p class="text-[11px] font-bold uppercase tracking-wider text-outline mb-2">{{ __('superadmin.guides.contents') }}</p>
            <ol class="space-y-0.5 text-[13px]">
                @foreach ($articles as $item)
                    <li>
                        <a href="{{ route('superadmin.guides.show', $item['slug']) }}" @if ($item['slug'] === $article['slug']) aria-current="page" @endif
                            class="flex gap-2 rounded-lg px-2 py-1.5 {{ $item['slug'] === $article['slug'] ? 'bg-primary/10 font-semibold text-primary' : 'text-on-surface-variant hover:bg-surface-container-low' }}">
                            <span class="w-5 shrink-0 text-right text-outline">{{ $item['number'] }}.</span><span>{{ preg_replace('/^\d+\.\s*/', '', $item['title']) }}</span>
                        </a>
                    </li>
                @endforeach
            </ol>
        </nav>

        <div class="space-y-4 min-w-0">
            <article class="sa-guide-paper">
                <div class="guide-article">{!! $article['html'] !!}</div>
            </article>
            <div class="flex flex-wrap justify-between gap-2">
                @if ($previous)
                    <a href="{{ route('superadmin.guides.show', $previous['slug']) }}" class="sa-button secondary"><span class="material-symbols-outlined text-[16px]">arrow_back</span>{{ $previous['title'] }}</a>
                @else
                    <span></span>
                @endif
                @if ($next)
                    <a href="{{ route('superadmin.guides.show', $next['slug']) }}" class="sa-button secondary">{{ $next['title'] }}<span class="material-symbols-outlined text-[16px]">arrow_forward</span></a>
                @endif
            </div>
        </div>
    </div>
@endsection
