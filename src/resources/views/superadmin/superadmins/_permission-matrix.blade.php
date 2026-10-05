{{-- Permission checkboxes with their scope; shared by the Superadmin page and the role form. Needs $permissionGroups, $grants, $canManage. --}}
<fieldset class="space-y-4" @disabled(! $canManage)>
    @foreach ($permissionGroups as $group => $permissions)
        <div class="border border-outline-variant rounded-xl p-3">
            <h3 class="text-xs font-bold text-on-surface mb-2">{{ $permissions->first()->groupLabel() }}</h3>
            <div class="space-y-1.5">
                @foreach ($permissions as $permission)
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <label class="flex items-center gap-2 text-xs text-on-surface cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="{{ $permission->value }}" @checked(array_key_exists($permission->value, $grants))>
                            <span>{{ $permission->label() }} <code class="text-xs text-outline">{{ $permission->value }}</code></span>
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
</fieldset>
