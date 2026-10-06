{{-- Permission checkboxes with their scope; shared by the Superadmin page and the role form. Needs $permissionGroups, $grants, $canManage.
     Group / global "select all" toggles are wired by resources/js/superadmin/permission-matrix.js. --}}
<fieldset class="space-y-3" data-permission-matrix data-count-template="{{ __('superadmin.permissions.selected_count', ['checked' => ':checked', 'total' => ':total']) }}" @disabled(! $canManage)>
    @if ($canManage)
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs text-outline" data-permission-count aria-live="polite"></p>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="sa-button secondary sm" data-permission-toggle-all="1">
                    <span class="material-symbols-outlined text-[16px]">select_check_box</span>{{ __('superadmin.permissions.select_all') }}
                </button>
                <button type="button" class="sa-button secondary sm" data-permission-toggle-all="0">
                    <span class="material-symbols-outlined text-[16px]">check_box_outline_blank</span>{{ __('superadmin.permissions.clear_all') }}
                </button>
            </div>
        </div>
    @endif
    <div class="grid grid-cols-1 lg:grid-cols-2 2xl:grid-cols-3 gap-3 items-start">
        @foreach ($permissionGroups as $group => $permissions)
            <div class="border border-outline-variant rounded-xl p-3" data-permission-group="{{ $group }}">
                <label class="flex items-center gap-2 mb-2 pb-2 border-b border-outline-variant/60 cursor-pointer">
                    <input type="checkbox" data-permission-group-toggle aria-label="{{ __('superadmin.permissions.select_group', ['group' => $permissions->first()->groupLabel()]) }}">
                    <span class="text-xs font-bold text-on-surface">{{ $permissions->first()->groupLabel() }}</span>
                    <span class="ml-auto text-[11px] text-outline" data-permission-group-count></span>
                </label>
                <div class="space-y-1.5">
                    @foreach ($permissions as $permission)
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <label class="flex items-center gap-2 text-xs text-on-surface cursor-pointer min-w-0">
                                <input type="checkbox" name="permissions[]" value="{{ $permission->value }}" data-permission-item @checked(array_key_exists($permission->value, $grants))>
                                <span class="min-w-0">{{ $permission->label() }} <code class="text-xs text-outline">{{ $permission->value }}</code></span>
                            </label>
                            @if ($permission->supportsScope())
                                <select name="scopes[{{ $permission->value }}]" class="sa-input !min-w-0 !py-1 text-xs" aria-label="{{ __('superadmin.superadmins.scope') }}">
                                    @foreach (\App\Enums\PermissionScope::cases() as $scope)
                                        <option value="{{ $scope->value }}" @selected(($grants[$permission->value] ?? 'all') === $scope->value)>{{ __('superadmin.permissions.scopes.'.$scope->value) }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</fieldset>
