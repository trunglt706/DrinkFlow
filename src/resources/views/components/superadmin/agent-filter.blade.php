{{-- "Agent" filter of a superadmin list (GET form field). Lists only the Agents visible for the page's permission
     (AgentDirectoryService::filterOptions), so it never names an Agent outside the signed-in Superadmin's scope.
     `selected` is the current Agent ID (null = all); extra attributes (e.g. data-* hooks) go on the <select>.
     Renders nothing for a Superadmin without that permission. --}}
@props(['permission', 'selected' => null, 'name' => 'agent'])
@php
    $agentFilterActor = auth('superadmin')->user();
    $agentFilterAllowed = $agentFilterActor?->hasPermission(\App\Enums\Permission::from($permission)) ?? false;
    $agentFilterOptions = app(\App\Services\Authorization\AgentDirectoryService::class)
        ->filterOptions($agentFilterActor, \App\Enums\Permission::from($permission));
@endphp
@if ($agentFilterAllowed)
<select name="{{ $name }}" {{ $attributes->merge(['class' => 'sa-input']) }} aria-label="{{ __('platform.subscriptions.agent') }}">
    <option value="">{{ __('superadmin.common.all_agents') }}</option>
    @foreach ($agentFilterOptions as $agentOption)
        <option value="{{ $agentOption->id }}" @selected((int) $selected === (int) $agentOption->id)>{{ $agentOption->name }}@if ($agentOption->company) · {{ $agentOption->company }}@endif</option>
    @endforeach
</select>
@endif
