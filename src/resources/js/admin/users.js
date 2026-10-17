import { formatMoney } from '../shared/money';
import { escapeHtml } from '../shared/escape-html';

/**
 * Admin Room Users & Devices Controller
 */
export function initAdminUsers() {
    const searchInput = document.querySelector('#user-search');
    const filterForm = document.querySelector('#users-filter-form');
    const statusSelect = document.querySelector('#user-status-filter');
    const rows = document.querySelectorAll('[data-user-row]');
    const modal = document.querySelector('#device-modal');
    const modalBackdrop = document.querySelector('#device-backdrop');
    const modalBody = document.querySelector('#device-modal-body');
    const deviceRevokeLabel = modal?.dataset.revokeLabel || 'Revoke access';
    const deviceRevokedLabel = modal?.dataset.revokedLabel || 'Revoked';
    const noDeviceLabel = modal?.dataset.noDeviceLabel || 'No device registered';
    const deviceFallbackLabel = modal?.dataset.deviceFallbackLabel || 'Device';
    const deviceLoadErrorLabel = modal?.dataset.loadErrorLabel || 'An error occurred. Please try again.';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const roomSlug = document.querySelector('[data-room-slug]')?.dataset.roomSlug || window.__DF_ROOM_SLUG__ || '';
    const actionModal = document.querySelector('#user-action-modal');
    const actionTitle = document.querySelector('#user-action-title');
    const actionMessage = document.querySelector('#user-action-message');
    const actionConfirm = document.querySelector('#user-action-confirm');
    const actionCancel = document.querySelector('#user-action-cancel');
    const actionIconWrap = document.querySelector('#user-action-icon-wrap');
    const actionIcon = document.querySelector('#user-action-icon');
    const actionConfirmIcon = document.querySelector('#user-action-confirm-icon');
    const actionConfirmLabel = document.querySelector('#user-action-confirm-label');

    // Icon and colour of the shared confirmation modal for each kind of action.
    const ACTION_VARIANTS = {
        default: {
            icon: 'manage_accounts', confirmIcon: 'check',
            wrap: 'bg-primary/10 text-primary',
            confirm: 'bg-primary text-on-primary hover:bg-primary/90',
        },
        block: {
            icon: 'block', confirmIcon: 'block',
            wrap: 'bg-amber-100 text-amber-600',
            confirm: 'bg-amber-600 text-white hover:bg-amber-700',
        },
        unblock: {
            icon: 'lock_open', confirmIcon: 'lock_open',
            wrap: 'bg-emerald-100 text-emerald-700',
            confirm: 'bg-primary text-on-primary hover:bg-primary/90',
        },
        remove: {
            icon: 'person_remove', confirmIcon: 'delete',
            wrap: 'bg-red-100 text-red-600',
            confirm: 'bg-red-600 text-white hover:bg-red-700',
        },
    };
    ACTION_VARIANTS.restore = {
        icon: 'restore_from_trash', confirmIcon: 'restore_from_trash',
        wrap: 'bg-emerald-100 text-emerald-700',
        confirm: 'bg-primary text-on-primary hover:bg-primary/90',
    };
    const BULK_ACTION_VARIANT = { active: 'unblock', blocked: 'block', removed: 'remove' };
    let activeVariant = ACTION_VARIANTS.default;

    const applyActionVariant = (name) => {
        const next = ACTION_VARIANTS[name] || ACTION_VARIANTS.default;
        if (actionIconWrap) {
            actionIconWrap.classList.remove(...activeVariant.wrap.split(' '));
            actionIconWrap.classList.add(...next.wrap.split(' '));
        }
        if (actionConfirm) {
            actionConfirm.classList.remove(...activeVariant.confirm.split(' '));
            actionConfirm.classList.add(...next.confirm.split(' '));
        }
        if (actionIcon) actionIcon.textContent = next.icon;
        if (actionConfirmIcon) actionConfirmIcon.textContent = next.confirmIcon;
        activeVariant = next;
    };

    const setConfirmProcessing = (processing) => {
        if (actionConfirmLabel) {
            actionConfirmLabel.textContent = processing
                ? (actionModal.dataset.processingLabel || 'Processing...')
                : (actionModal.dataset.confirmLabel || 'Confirm');
        }
        if (actionConfirmIcon) {
            actionConfirmIcon.textContent = processing ? 'progress_activity' : activeVariant.confirmIcon;
            actionConfirmIcon.classList.toggle('animate-spin', processing);
        }
    };
    const bulkToolbar = document.querySelector('#users-bulk-toolbar');
    const selectAllUsers = document.querySelector('#users-select-all');
    const bulkSelectedCount = document.querySelector('#users-selected-count');
    let pendingAction = null;

    // Create / Add User Modal Elements
    const createUserModal = document.querySelector('#create-user-modal');
    const createUserBackdrop = document.querySelector('#create-user-backdrop');
    const createUserClose = document.querySelector('#create-user-close');
    const createUserCancel = document.querySelector('#create-user-cancel');
    const btnOpenCreateUserModal = document.querySelector('#btn-open-create-user-modal');
    const createUserForm = document.querySelector('#create-user-form');
    const createUserError = document.querySelector('#create-user-error');
    const createUserSubmit = document.querySelector('#create-user-submit');

    // User Detail Modal Elements
    const userDetailModal = document.querySelector('#user-detail-modal');
    const userDetailBackdrop = document.querySelector('#user-detail-backdrop');
    const userDetailClose = document.querySelector('#user-detail-close');
    const userDetailCloseBtn = document.querySelector('#user-detail-close-btn');
    const userDetailLoading = document.querySelector('#user-detail-loading');
    const userDetailError = document.querySelector('#user-detail-error');
    const userDetailContent = document.querySelector('#user-detail-content');
    let currentDetailUser = null;

    if (!searchInput && !rows.length && !modal && !createUserModal && !userDetailModal) return;

    const selectedUserCheckboxes = () => Array.from(document.querySelectorAll('[data-user-select]:checked'));
    const updateBulkSelection = () => {
        const checkboxes = Array.from(document.querySelectorAll('[data-user-select]'));
        const selected = selectedUserCheckboxes();
        if (bulkSelectedCount) bulkSelectedCount.textContent = String(selected.length);
        if (bulkToolbar) {
            bulkToolbar.classList.toggle('hidden', selected.length === 0);
            bulkToolbar.classList.toggle('flex', selected.length > 0);
        }
        if (selectAllUsers) {
            selectAllUsers.checked = checkboxes.length > 0 && selected.length === checkboxes.length;
            selectAllUsers.indeterminate = selected.length > 0 && selected.length < checkboxes.length;
        }
    };

    selectAllUsers?.addEventListener('change', () => {
        document.querySelectorAll('[data-user-select]').forEach((checkbox) => {
            checkbox.checked = selectAllUsers.checked;
        });
        updateBulkSelection();
    });
    document.addEventListener('change', (event) => {
        if (event.target.matches('[data-user-select]')) updateBulkSelection();
    });

    document.addEventListener('click', async (event) => {
        const bulkButton = event.target.closest('[data-bulk-user-action]');
        if (!bulkButton) return;
        const selected = selectedUserCheckboxes();
        if (!selected.length) return;
        openActionModal(
            actionModal?.dataset.bulkTitle || 'Confirm bulk action',
            bulkToolbar?.dataset.confirmMessage || 'Confirm bulk action?',
            async () => {
                try {
                    const response = await fetch(`/admin/${roomSlug}/room-users/bulk-action`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            action: bulkButton.dataset.bulkUserAction,
                            room_user_ids: selected.map((checkbox) => Number(checkbox.dataset.roomUserId)),
                        }),
                    });
                    if (!response.ok) {
                        const error = await response.json().catch(() => ({}));
                        throw new Error(error.message || 'Bulk action failed.');
                    }
                    window.location.reload();
                } catch (error) {
                    console.error(error);
                    window.alert(error.message || 'Bulk action failed.');
                }
            },
            BULK_ACTION_VARIANT[bulkButton.dataset.bulkUserAction] || 'default',
        );
    });

    // Backend Form Search & Status Trigger (No client-side DOM row filtering)
    statusSelect?.addEventListener('change', () => {
        filterForm?.requestSubmit();
    });
    searchInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            filterForm?.requestSubmit();
        }
    });
    searchInput?.addEventListener('admin:search-cleared', () => {
        filterForm?.requestSubmit();
    });

    const closeDeviceModal = () => {
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    };
    window.closeDeviceModal = closeDeviceModal;
    modalBackdrop?.addEventListener('click', closeDeviceModal);

    const closeActionModal = () => {
        actionModal?.classList.add('hidden');
        actionModal?.classList.remove('flex');
        pendingAction = null;
    };

    actionCancel?.addEventListener('click', closeActionModal);
    actionModal?.addEventListener('click', (event) => {
        if (event.target === actionModal) closeActionModal();
    });

    const openActionModal = (title, message, action, variant = 'default') => {
        if (!actionModal || !actionConfirm) return;
        actionTitle.textContent = title;
        actionMessage.textContent = message;
        applyActionVariant(variant);
        setConfirmProcessing(false);
        pendingAction = action;
        actionModal.classList.remove('hidden');
        actionModal.classList.add('flex');
    };

    actionConfirm?.addEventListener('click', async () => {
        if (!pendingAction) return;
        const action = pendingAction;
        actionConfirm.disabled = true;
        actionConfirm.classList.add('opacity-60', 'cursor-not-allowed');
        setConfirmProcessing(true);
        try {
            await action();
        } finally {
            actionConfirm.disabled = false;
            actionConfirm.classList.remove('opacity-60', 'cursor-not-allowed');
            setConfirmProcessing(false);
        }
    });

    // -------------------------------------------------------------
    // Device Trust Management Modal
    // -------------------------------------------------------------
    const openTrustModalFn = async function(roomUserId, memberName) {
        if (!modal || !modalBody) return;
        const titleEl = document.querySelector('#device-modal-title');
        if (titleEl) titleEl.textContent = `${memberName}`;
        modalBody.innerHTML = '<div class="py-8 text-center text-outline text-xs"><span class="material-symbols-outlined animate-spin text-[24px]">progress_activity</span></div>';
        modal.classList.remove('hidden');
        modal.classList.add('flex');

        try {
            const res = await fetch(`/admin/${roomSlug}/room-users/${roomUserId}`, {
                headers: { 'Accept': 'application/json' }
            });
            const { data: user } = await res.json();

            if (!user.devices || user.devices.length === 0) {
                modalBody.innerHTML = `
                    <div class="py-8 text-center text-outline">
                        <span class="material-symbols-outlined text-3xl mb-1 text-outline-variant">smartphone</span>
                        <p class="text-xs">${noDeviceLabel}</p>
                    </div>
                `;
                return;
            }

            modalBody.innerHTML = user.devices.map(dev => `
                <div class="flex items-center justify-between p-3 bg-surface-container-low border border-outline-variant rounded-lg text-xs">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-outlined text-[20px] text-primary">laptop_mac</span>
                        <div>
                            <div class="font-mono font-bold text-on-surface">${escapeHtml(dev.device_uuid || dev.device_name || deviceFallbackLabel)}</div>
                            <div class="text-[11px] text-outline">${escapeHtml(dev.last_seen_at || '—')}</div>
                        </div>
                    </div>
                    <div>
                        ${dev.status === 'revoked' ? `
                            <span class="px-2 py-0.5 rounded bg-rose-50 text-rose-700 text-[10px] font-semibold border border-rose-200">${deviceRevokedLabel}</span>
                        ` : `
                            <button type="button" data-revoke-device data-room-user-id="${escapeHtml(roomUserId)}" data-device-id="${escapeHtml(dev.id)}" class="px-2.5 py-1 bg-rose-50 text-rose-700 hover:bg-rose-100 rounded text-[11px] font-semibold border border-rose-200 transition-colors cursor-pointer">
                                ${deviceRevokeLabel}
                            </button>
                        `}
                    </div>
                </div>
            `).join('');
        } catch(e) {
            console.error(e);
            modalBody.innerHTML = `<div class="py-8 text-center text-error text-xs">${deviceLoadErrorLabel}</div>`;
        }
    };

    window.openDeviceTrustModal = openTrustModalFn;
    window.openDeviceModal = openTrustModalFn;

    // -------------------------------------------------------------
    // User Detail Modal Controller
    // -------------------------------------------------------------
    const closeUserDetailModal = () => {
        if (!userDetailModal) return;
        userDetailModal.classList.add('hidden');
        userDetailModal.classList.remove('flex');
        currentDetailUser = null;
    };
    window.closeUserDetailModal = closeUserDetailModal;

    userDetailClose?.addEventListener('click', closeUserDetailModal);
    userDetailCloseBtn?.addEventListener('click', closeUserDetailModal);
    userDetailBackdrop?.addEventListener('click', closeUserDetailModal);

    const openUserDetailModal = async (roomUserId) => {
        if (!userDetailModal) return;

        // Reset state
        userDetailLoading?.classList.remove('hidden');
        userDetailError?.classList.add('hidden');
        userDetailContent?.classList.add('hidden');
        currentDetailUser = null;

        userDetailModal.classList.remove('hidden');
        userDetailModal.classList.add('flex');

        try {
            const res = await fetch(`/admin/${roomSlug}/room-users/${roomUserId}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (!res.ok) throw new Error('Failed to load user detail');

            const { data: user } = await res.json();
            currentDetailUser = user;

            // Translation Maps from dataset
            const statusMap = {
                active: userDetailModal.dataset.statusActive || '',
                blocked: userDetailModal.dataset.statusBlocked || '',
                pending: userDetailModal.dataset.statusPending || '',
                removed: userDetailModal.dataset.statusRemoved || '',
            };
            const roleMap = {
                owner: userDetailModal.dataset.roleOwner || '',
                admin: userDetailModal.dataset.roleAdmin || '',
                member: userDetailModal.dataset.roleMember || '',
            };

            // Populate Header
            const name = user.display_name || user.global_user?.name || 'Member #' + user.id;
            const email = user.global_user?.email || '—';
            const role = user.role || 'member';
            const status = user.status || 'active';
            const avatarUrl = user.global_user?.avatar_url || '';

            const nameEl = document.querySelector('#user-detail-name');
            const emailEl = document.querySelector('#user-detail-email');
            const emailCopyEl = document.querySelector('#user-detail-email-copy');
            const codeEl = document.querySelector('#user-detail-code');
            const roleBadge = document.querySelector('#user-detail-role-badge');
            const statusBadge = document.querySelector('#user-detail-status-badge');
            const avatarImg = document.querySelector('#user-detail-avatar-img');
            const avatarInitial = document.querySelector('#user-detail-avatar-initial');

            if (nameEl) nameEl.textContent = name;
            if (emailEl) emailEl.textContent = email;
            if (emailCopyEl) {
                const realEmail = user.global_user?.email || '';
                emailCopyEl.dataset.copy = realEmail;
                emailCopyEl.classList.toggle('hidden', !realEmail);
            }
            if (codeEl) codeEl.textContent = user.user_code || '—';

            if (roleBadge) {
                roleBadge.textContent = roleMap[role] || role;
                roleBadge.className = 'text-[11px] font-semibold px-2 py-0.5 rounded border ' + (
                    role === 'owner' ? 'bg-amber-50 text-amber-800 border-amber-300' :
                    role === 'admin' ? 'bg-purple-50 text-purple-700 border-purple-200' :
                    'bg-surface-container text-secondary border-outline-variant'
                );
            }

            if (statusBadge) {
                statusBadge.textContent = statusMap[status] || status;
                statusBadge.className = 'text-[11px] font-semibold px-2 py-0.5 rounded border ' + (
                    status === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' :
                    'bg-rose-50 text-rose-700 border-rose-200'
                );
            }

            if (avatarImg && avatarInitial) {
                if (avatarUrl && !avatarUrl.includes('default-avatar.svg')) {
                    avatarImg.src = avatarUrl;
                    avatarImg.alt = name;
                    avatarImg.classList.remove('hidden');
                    avatarInitial.classList.add('hidden');
                } else {
                    avatarImg.classList.add('hidden');
                    avatarInitial.textContent = name.charAt(0).toUpperCase();
                    avatarInitial.classList.remove('hidden');
                }
            }

            // Populate Contact & Location
            const phoneEl = document.querySelector('#user-detail-phone');
            const phoneCopyEl = document.querySelector('#user-detail-phone-copy');
            const deskEl = document.querySelector('#user-detail-desk');
            const deliveryEl = document.querySelector('#user-detail-delivery');

            if (phoneEl) phoneEl.textContent = user.global_user?.phone || '—';
            if (phoneCopyEl) {
                const realPhone = user.global_user?.phone || '';
                phoneCopyEl.dataset.copy = realPhone;
                phoneCopyEl.classList.toggle('hidden', !realPhone);
            }
            if (deskEl) deskEl.textContent = user.global_user?.desk_location || '—';
            const birthdayEl = document.querySelector('#user-detail-birthday');
            if (birthdayEl) birthdayEl.textContent = user.global_user?.birthday || '—';
            if (deliveryEl) deliveryEl.textContent = user.global_user?.delivery_location || '—';

            // Populate Activity & Membership
            const joinedEl = document.querySelector('#user-detail-joined');
            const lastActiveEl = document.querySelector('#user-detail-last-active');
            const totalOrdersEl = document.querySelector('#user-detail-total-orders');
            const totalDebtEl = document.querySelector('#user-detail-total-debt');

            if (joinedEl) joinedEl.textContent = user.created_at || '—';
            if (lastActiveEl) lastActiveEl.textContent = user.last_active_at || '—';
            if (totalOrdersEl) totalOrdersEl.textContent = String(user.total_orders ?? 0);
            if (totalDebtEl) {
                const debt = Number(user.total_debt || 0);
                if (debt > 0) {
                    totalDebtEl.textContent = formatMoney(debt);
                    totalDebtEl.className = 'font-bold text-sm font-mono mt-0.5 text-rose-600';
                } else {
                    totalDebtEl.textContent = formatMoney(0);
                    totalDebtEl.className = 'font-bold text-sm font-mono mt-0.5 text-emerald-600';
                }
            }

            // Show Content
            userDetailLoading?.classList.add('hidden');
            userDetailContent?.classList.remove('hidden');

        } catch (err) {
            console.error('Failed to load user detail:', err);
            userDetailLoading?.classList.add('hidden');
            userDetailError?.classList.remove('hidden');
        }
    };

    // Event Delegations
    document.addEventListener('click', (event) => {
        const userDetailBtn = event.target.closest('[data-open-user-detail]');
        if (userDetailBtn) {
            openUserDetailModal(userDetailBtn.dataset.roomUserId);
            return;
        }

        const devicesButton = event.target.closest('[data-open-devices]');
        if (devicesButton) {
            openTrustModalFn(devicesButton.dataset.roomUserId, devicesButton.dataset.memberName || 'Member');
            return;
        }

        const statusButton = event.target.closest('[data-toggle-user-status]');
        if (statusButton) {
            window.toggleUserStatus(Number(statusButton.dataset.roomUserId), statusButton.dataset.newStatus);
            return;
        }

        const restoreButton = event.target.closest('[data-restore-room-user]');
        if (restoreButton) {
            window.restoreRoomUser(Number(restoreButton.dataset.roomUserId));
            return;
        }

        const removeButton = event.target.closest('[data-remove-room-user]');
        if (removeButton) {
            window.removeRoomUser(Number(removeButton.dataset.roomUserId));
            return;
        }

        const revokeButton = event.target.closest('[data-revoke-device]');
        if (revokeButton) {
            window.revokeDevice(Number(revokeButton.dataset.roomUserId), Number(revokeButton.dataset.deviceId));
            return;
        }

        if (event.target.closest('[data-close-device-modal]')) closeDeviceModal();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (userDetailModal && !userDetailModal.classList.contains('hidden')) {
                closeUserDetailModal();
            }
            if (modal && !modal.classList.contains('hidden')) {
                closeDeviceModal();
            }
            if (createUserModal && !createUserModal.classList.contains('hidden')) {
                closeCreateUserModal();
            }
            if (actionModal && !actionModal.classList.contains('hidden')) {
                closeActionModal();
            }
        }
    });

    window.revokeDevice = async function(roomUserId, deviceId) {
        if (!confirm('Revoke this device?')) return;
        try {
            const res = await fetch(`/admin/${roomSlug}/room-users/${roomUserId}/devices/${deviceId}/revoke`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
            });
            if (res.ok) {
                closeDeviceModal();
                window.location.reload();
            } else {
                alert('Could not revoke device.');
            }
        } catch(e) {
            console.error(e);
        }
    };

    window.toggleUserStatus = async function(roomUserId, newStatus) {
        const isBlocking = newStatus === 'blocked';
        openActionModal(
            actionModal?.dataset.statusTitle || 'Toggle User Status',
            actionModal?.dataset[isBlocking ? 'blockMessage' : 'unblockMessage'] || 'Confirm status change?',
            async () => {
                try {
                    const res = await fetch(`/admin/${roomSlug}/room-users/${roomUserId}/status`, {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                        body: JSON.stringify({ status: newStatus })
                    });
                    if (res.ok) {
                        window.location.reload();
                    } else {
                        const err = await res.json().catch(() => ({}));
                        const msg = err.errors ? Object.values(err.errors).flat().join('\n') : (err.message || 'Error updating status');
                        alert(msg);
                    }
                } catch (e) {
                    console.error(e);
                    alert('An error occurred.');
                }
            },
            isBlocking ? 'block' : 'unblock'
        );
    };

    window.removeRoomUser = async function(roomUserId) {
        openActionModal(
            actionModal?.dataset.removeTitle || 'Remove User',
            actionModal?.dataset.removeMessage || 'Remove this user from the room?',
            async () => {
                try {
                    const res = await fetch(`/admin/${roomSlug}/room-users/${roomUserId}`, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
                    });
                    if (res.ok) {
                        window.location.reload();
                    } else {
                        const err = await res.json().catch(() => ({}));
                        const msg = err.errors ? Object.values(err.errors).flat().join('\n') : (err.message || 'Error removing user');
                        alert(msg);
                    }
                } catch (e) {
                    console.error(e);
                    alert('An error occurred.');
                }
            },
            'remove'
        );
    };

    window.restoreRoomUser = async function(roomUserId) {
        openActionModal(
            actionModal?.dataset.restoreTitle || 'Restore User',
            actionModal?.dataset.restoreMessage || 'Restore this user to the room?',
            async () => {
                try {
                    const res = await fetch(`/admin/${roomSlug}/room-users/${roomUserId}/restore`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
                    });
                    if (res.ok) {
                        window.location.reload();
                    } else {
                        const err = await res.json().catch(() => ({}));
                        const msg = err.errors ? Object.values(err.errors).flat().join('\n') : (err.message || 'Error restoring user');
                        alert(msg);
                    }
                } catch (e) {
                    console.error(e);
                    alert('An error occurred.');
                }
            },
            'restore'
        );
    };

    // Create User Modal Handlers
    const openCreateUserModal = () => {
        if (!createUserModal) return;
        createUserForm?.reset();
        if (createUserError) {
            createUserError.textContent = '';
            createUserError.classList.add('hidden');
        }
        createUserModal.classList.remove('hidden');
        createUserModal.classList.add('flex');
        document.querySelector('#create-user-email')?.focus();
    };

    const closeCreateUserModal = () => {
        if (!createUserModal) return;
        createUserModal.classList.add('hidden');
        createUserModal.classList.remove('flex');
        createUserForm?.reset();
    };

    btnOpenCreateUserModal?.addEventListener('click', openCreateUserModal);
    createUserClose?.addEventListener('click', closeCreateUserModal);
    createUserCancel?.addEventListener('click', closeCreateUserModal);
    createUserBackdrop?.addEventListener('click', closeCreateUserModal);

    createUserForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!createUserSubmit) return;

        const email = document.querySelector('#create-user-email')?.value.trim() || '';
        const name = document.querySelector('#create-user-name')?.value.trim() || '';
        const phone = document.querySelector('#create-user-phone')?.value.trim() || '';
        const deskLocation = document.querySelector('#create-user-desk')?.value.trim() || '';
        const birthday = document.querySelector('#create-user-birthday')?.value || '';

        if (!email || !name) return;

        if (createUserError) {
            createUserError.textContent = '';
            createUserError.classList.add('hidden');
        }

        createUserSubmit.disabled = true;
        createUserSubmit.classList.add('opacity-60', 'cursor-not-allowed');

        try {
            const res = await fetch(`/admin/${roomSlug}/room-users`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    email,
                    name,
                    phone: phone || null,
                    desk_location: deskLocation || null,
                    birthday: birthday || null,
                }),
            });

            const data = await res.json().catch(() => ({}));

            if (res.ok && data.success) {
                closeCreateUserModal();
                window.location.reload();
            } else {
                let errorLines = ['An error occurred while adding user.'];
                if (data.errors) {
                    errorLines = Object.values(data.errors).flat();
                } else if (data.message) {
                    errorLines = [data.message];
                }
                if (createUserError) {
                    // Server messages can echo user input (e.g. the e-mail domain), so they must be encoded.
                    createUserError.innerHTML = errorLines.map(escapeHtml).join('<br>');
                    createUserError.classList.remove('hidden');
                } else {
                    alert(errorLines.join('\n'));
                }
            }
        } catch (err) {
            console.error(err);
            if (createUserError) {
                createUserError.textContent = 'Network error. Please try again.';
                createUserError.classList.remove('hidden');
            }
        } finally {
            createUserSubmit.disabled = false;
            createUserSubmit.classList.remove('opacity-60', 'cursor-not-allowed');
        }
    });
}
