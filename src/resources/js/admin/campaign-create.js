import { formatMoney } from '../shared/money';

/**
 * Whole-percent sponsor shares from stored allocations (older campaigns may hold decimals such as
 * 33.33): each share is rounded down and the lost fraction goes to the first sponsor, so a set that
 * totalled 100% still does.
 *
 * @param {Array<{percentage?: number|string}>} allocations Stored allocations.
 * @returns {number[]} Whole percents, in the same order.
 */
const wholeSponsorPercentages = (allocations) => {
    const raw = allocations.map((allocation) => Math.max(0, Number(allocation.percentage) || 0));
    const whole = raw.map((value) => Math.floor(value));
    const leftover = Math.round(raw.reduce((sum, value) => sum + value, 0)) - whole.reduce((sum, value) => sum + value, 0);
    if (whole.length > 0 && leftover > 0) whole[0] = Math.min(100, whole[0] + leftover);

    return whole;
};

/**
 * Admin Campaign Creator & Editor (Alpine Component)
 */
export function campaignCreateComponent(defaults = {}, availableRoomUsers = [], initialCampaign = null) {
    const page = document.querySelector('#campaign-create-page, #campaign-edit-page');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const getRoomSlug = () => document.body?.dataset.roomSlug || window.__DF_ROOM_SLUG__ || '';
    const messages = JSON.parse(page?.dataset.messages || '{}');
    const msg = (key, replacements = {}) => Object.entries(replacements).reduce(
        (text, [name, value]) => text.replaceAll(`:${name}`, value),
        messages[key] || '',
    );
    const budgetErrorTemplate = page?.dataset.budgetError || 'Campaign budget exceeds :limit.';
    const sponsorPercentageError = page?.dataset.sponsorPercentageError || 'The total sponsorship percentage must equal 100%.';
    const itemDeletedSuccess = page?.dataset.itemDeletedSuccess || 'Item ":name" was deleted successfully.';
    const categoryDeletedSuccess = page?.dataset.categoryDeletedSuccess || 'Category ":category" and :count item(s) were deleted.';
    const storeUrl = page?.dataset.storeUrl || page?.dataset.submitUrl || '';
    const submitMethod = page?.dataset.submitMethod || (initialCampaign ? 'PATCH' : 'POST');
    const isEditMode = Boolean(initialCampaign || page?.dataset.isEdit === 'true');
    const imageUploadUrl = page?.dataset.imageUploadUrl || '';
    const defaultMenuCategory = page?.dataset.defaultMenuCategory || 'General items';
    const crawlerMenuCategory = page?.dataset.crawlerMenuCategory || 'Crawled items';
    const onlineRestaurantName = page?.dataset.onlineRestaurantName || 'Online restaurant';
    const otherCategory = page?.dataset.defaultCategoryOther || 'Other';
    let itemCategorySuggestions = [];
    try {
        itemCategorySuggestions = JSON.parse(page?.dataset.itemCategories || '[]');
    } catch {
        itemCategorySuggestions = [];
    }
    const parseCleanNumber = val => {
        if (typeof val === 'number') return Number.isFinite(val) ? Math.round(val) : 0;
        const cleaned = String(val || '').replace(/\D/g, '');
        return cleaned ? parseInt(cleaned, 10) : 0;
    };

    const formatCurrencyDisplay = val => {
        if (val === null || val === undefined || val === '') return '';
        const num = parseCleanNumber(val);
        if (num === 0) return '0';
        return String(num).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    };

    /**
     * Format a datetime-local value (YYYY-MM-DDTHH:mm) as dd/MM/yyyy HH:mm, matching channel notifications.
     */
    const formatDateTimeDisplay = value => {
        const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})[T\s](\d{2}):(\d{2})/);
        if (!match) return '—';
        const [, year, month, day, hour, minute] = match;
        return `${day}/${month}/${year} ${hour}:${minute}`;
    };

    const normalizeImageUrl = value => {
        const imageUrl = String(value || '').trim();
        if (!imageUrl) return '';

        if (imageUrl.startsWith('storage/')) {
            return `/${imageUrl}`;
        }

        try {
            const parsedUrl = new URL(imageUrl, window.location.origin);
            if (parsedUrl.pathname.startsWith('/storage/')) {
                return `${parsedUrl.pathname}${parsedUrl.search}${parsedUrl.hash}`;
            }
        } catch (_) {
            return imageUrl;
        }

        return imageUrl;
    };
    const normalizeSearch = value => String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'D')
        .toLowerCase()
        .trim();
    const roomUsers = availableRoomUsers.map(user => ({
        ...user,
        id: String(user.id),
        label: user.name || user.user_code || '',
        search: normalizeSearch(`${user.name || ''} ${user.user_code || ''}`)
    }));
    const campaignSettings = {
        name: defaults.name || '',
        max_budget: Number(defaults.max_budget) || 0,
        payment_account_id: String(defaults.payment_account_id || '')
    };

    return {
        campaignSettings,
        isEditMode,
        menuTab: initialCampaign ? 'reuse' : 'reuse',
        selectedPreviousCampaignId: null,
        showConfirmModal: false,
        pendingStatus: 'active',
        showNotifyModal: false,
        notifyMembers: true,
        showAddItemModal: false,
        editingItemIndex: null,
        itemModalTab: 'basic',
        itemSubmitting: false,
        menuView: 'category',
        selectedCategory: '',
        menuSearchInput: '',
        menuSearch: '',
        menuSearchTimer: null,
        itemCategories: itemCategorySuggestions,
        newItem: { id: null, name: '', price: 0, category: otherCategory, description: '', image_url: '', status: 'active', toppings: [], options: [] },
        imageUploading: false,
        selectedImageFileName: '',
        sponsors: [],
        roomUsers,
        submitting: false,
        submittingAction: null,
        showCancelCampaignModal: false,
        cancelSubmitting: false,
        crawlerUrl: '',
        crawlerLoading: false,
        crawlerMessage: '',
        rawJson: '',
        form: {
            name: initialCampaign?.name || campaignSettings.name,
            restaurant: initialCampaign?.restaurant || '',
            deadline: initialCampaign?.deadline || '',
            payment_account_id: initialCampaign?.payment_account_id ? String(initialCampaign.payment_account_id) : campaignSettings.payment_account_id,
            description: initialCampaign?.description || '',
            sponsor_type: initialCampaign?.sponsor_type || 'none',
            sponsor_description: initialCampaign?.sponsor_description || '',
            max_budget: initialCampaign?.max_budget ?? campaignSettings.max_budget,
            delivery_fee: initialCampaign?.delivery_fee ?? '',
            discount: initialCampaign?.discount ?? '',
            self_paid_price_basis: initialCampaign?.self_paid_price_basis || 'original',
            flat_price: initialCampaign?.flat_price ?? '',
            status: initialCampaign?.status || 'active'
        },
        menuItems: [],
        presets: {
            highlands: {
                name: 'Highlands Coffee - Giờ Giải Lao',
                restaurant: 'Highlands Coffee',
                items: [
                    { name: 'Phin Sữa Đá Size M', price: 35000, category: 'Cà Phê Phin' },
                    { name: 'Trà Sen Vàng Củ Năng', price: 49000, category: 'Trà Đặc Biệt' },
                    { name: 'Freeze Trà Xanh Thạch', price: 55000, category: 'Freeze' },
                    { name: 'Bánh Mì Thịt Nướng', price: 25000, category: 'Bánh Mì' }
                ]
            },
            phuclong: {
                name: 'Phúc Long Tea & Coffee',
                restaurant: 'Phúc Long Tea',
                items: [
                    { name: 'Trà Sữa Phúc Long Size L', price: 55000, category: 'Trà Sữa' },
                    { name: 'Trà Đào Cam Sả', price: 50000, category: 'Trà Trái Cây' },
                    { name: 'Trà Ô Long Sữa', price: 50000, category: 'Trà Sữa' }
                ]
            },
            gongcha: {
                name: 'Gong Cha Trà Sữa Thượng Hạng',
                restaurant: 'Gong Cha',
                items: [
                    { name: 'Trà Sữa Trân Châu Hoàng Kim', price: 55000, category: 'Trà Sữa' },
                    { name: 'Alisan Milk Foam', price: 52000, category: 'Milk Foam' },
                    { name: 'Trà Đen Macchiato', price: 48000, category: 'Macchiato' }
                ]
            },
            tocotoco: {
                name: 'TocoToco Bubble Tea',
                restaurant: 'TocoToco',
                items: [
                    { name: 'Trà Sữa Trân Châu Sợi', price: 38000, category: 'Trà Sữa' },
                    { name: 'Trà Sữa Ba Anh Em', price: 45000, category: 'Trà Sữa' },
                    { name: 'Trà Xoài Bưởi Hồng', price: 42000, category: 'Trà Trái Cây' }
                ]
            },
            starbucks: {
                name: 'Starbucks Coffee Break',
                restaurant: 'Starbucks Vietnam',
                items: [
                    { name: 'Caramel Macchiato Grande', price: 85000, category: 'Espresso' },
                    { name: 'Green Tea Cream Frappuccino', price: 95000, category: 'Frappuccino' },
                    { name: 'Cold Brew Nitro', price: 90000, category: 'Cold Brew' }
                ]
            },
            comtam: {
                name: 'Cơm Tấm Phúc Lộc Gia - Bữa Trưa',
                restaurant: 'Cơm Tấm Phúc Lộc Gia',
                items: [
                    { name: 'Cơm Sườn Nướng Mỡ Hành', price: 45000, category: 'Cơm Tấm' },
                    { name: 'Cơm Sườn Bì Chả Đặc Biệt', price: 60000, category: 'Cơm Tấm' },
                    { name: 'Canh Rong Biển Thịt Bằm', price: 15000, category: 'Canh' }
                ]
            }
        },

        init() {
            if (initialCampaign) {
                this.form.name = initialCampaign.name || '';
                this.form.restaurant = initialCampaign.restaurant || '';
                this.form.deadline = initialCampaign.deadline || '';
                this.form.payment_account_id = initialCampaign.payment_account_id ? String(initialCampaign.payment_account_id) : '';
                this.form.description = initialCampaign.description || '';
                this.form.sponsor_type = ['none', 'full'].includes(initialCampaign.sponsor_type) ? initialCampaign.sponsor_type : 'none';
                this.form.sponsor_description = initialCampaign.sponsor_description || '';
                this.form.self_paid_price_basis = ['original', 'campaign_prorated'].includes(initialCampaign.self_paid_price_basis) ? initialCampaign.self_paid_price_basis : 'original';
                this.form.max_budget = initialCampaign.max_budget ?? this.campaignSettings.max_budget;
                this.form.flat_price = initialCampaign.flat_price ?? '';
                this.form.status = initialCampaign.status || 'active';
                const initialAllocations = initialCampaign.sponsor_allocations || [];
                const initialPercentages = wholeSponsorPercentages(initialAllocations);
                this.sponsors = initialAllocations.map((alloc, index) => ({
                    user_id: String(alloc.room_user_id || ''),
                    percentage: initialPercentages[index],
                    search: this.roomUsers.find(u => u.id === String(alloc.room_user_id || ''))?.label || '',
                    open: false
                }));
                this.applyMenuItems(initialCampaign.items || []);
            } else {
                this.form.name = this.form.name || this.campaignSettings.name;
                this.form.max_budget = this.form.max_budget || this.campaignSettings.max_budget;
                this.form.payment_account_id = this.form.payment_account_id || this.campaignSettings.payment_account_id;
                this.setDeadlineMinutes(60);
            }

            window.addEventListener('drinkflow:apply-menu-items', (event) => {
                if (event.detail && Array.isArray(event.detail.items)) {
                    this.applyMenuItems(event.detail.items);
                }
            });

            // Internal menu analysis found the restaurant name: use it only when the field is still empty.
            window.addEventListener('drinkflow:suggest-restaurant', (event) => {
                const name = String(event.detail?.name || '').trim();
                if (name && !String(this.form.restaurant || '').trim()) {
                    this.form.restaurant = name;
                }
            });
        },

        setDeadlineMinutes(mins) {
            const d = new Date(Date.now() + mins * 60000);
            const pad = n => n.toString().padStart(2, '0');
            this.form.deadline = `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
        },

        applyPreset(key) {
            const p = this.presets[key];
            if (!p) return;
            this.form.name = p.name;
            this.form.restaurant = p.restaurant;
            this.applyMenuItems(p.items);
            this.menuTab = 'reuse';
        },

        /**
         * Replace the menu with imported items. Items without a name are dropped, so a category whose items are all
         * nameless (e.g. an empty category in an AI/.md/.json result) does not show up: categories come from items.
         *
         * @returns {number} Number of items kept.
         */
        applyMenuItems(items) {
            this.menuItems = this.importableMenuItems(items);
            this.syncSelectedCategory();
            return this.menuItems.length;
        },

        importableMenuItems(items) {
            return (Array.isArray(items) ? items : [])
                .filter(item => item && typeof item === 'object')
                .map(item => this.normalizeMenuItem(item))
                .filter(item => item.name.trim() !== '');
        },

        normalizeMenuItem(item) {
            return {
                id: item.id || null,
                name: String(item.name ?? '').trim(),
                price: parseCleanNumber(item.price ?? item.base_price),
                category: String(item.category ?? '').replace(/\s+/g, ' ').trim() || otherCategory,
                description: item.description || '',
                image_url: normalizeImageUrl(item.image_url),
                image_load_failed: false,
                status: item.status || 'active',
                toppings: (item.toppings || []).map(topping => ({
                    id: topping.id || null,
                    name: topping.name || '',
                    price: parseCleanNumber(topping.price)
                })),
                options: (item.options || item.sizes || []).map(option => ({
                    id: option.id || null,
                    name: option.name || '',
                    price_delta: parseCleanNumber(option.price_delta)
                }))
            };
        },

        addSponsor() {
            this.sponsors.push({ user_id: '', percentage: 0, search: '', open: false });
            this.distributeSponsorPercentages();
        },

        removeSponsor(index) {
            this.sponsors.splice(index, 1);
            this.distributeSponsorPercentages();
        },

        /**
         * Split 100% evenly across the sponsors in whole percents; the leftover goes to the first
         * sponsor (1 → 100, 2 → 50/50, 3 → 34/33/33).
         */
        distributeSponsorPercentages() {
            const count = this.sponsors.length;
            if (count === 0) return;
            const base = Math.floor(100 / count);
            const remainder = 100 - base * count;
            this.sponsors.forEach((sponsor, index) => {
                sponsor.percentage = base + (index === 0 ? remainder : 0);
            });
        },

        clampSponsorPercentage(sponsor) {
            if (String(sponsor.percentage) === '') return;
            const percentage = Number(sponsor.percentage);
            // Sponsorship shares are whole percents (the backend rejects decimals).
            sponsor.percentage = Number.isFinite(percentage)
                ? Math.min(100, Math.max(0, Math.trunc(percentage)))
                : 0;
        },

        filteredSponsorUsers(query) {
            const needle = normalizeSearch(query);
            return this.roomUsers
                .filter(user => !needle || user.search.includes(needle))
                .slice(0, 12);
        },

        selectSponsor(sponsor, user) {
            sponsor.user_id = user.id;
            sponsor.search = user.label;
            sponsor.open = false;
        },

        addMenuItem() {
            this.menuItems.push(this.normalizeMenuItem({ category: defaultMenuCategory }));
        },

        openAddItemModal() {
            this.editingItemIndex = null;
            this.itemModalTab = 'basic';
            this.selectedImageFileName = '';
            const initialCategory = this.menuView === 'category' && this.selectedCategory ? this.selectedCategory : '';
            this.newItem = {
                id: null,
                name: '',
                price: '',
                category: initialCategory,
                description: '',
                image_url: '',
                image_load_failed: false,
                status: 'active',
                toppings: [],
                options: []
            };
            this.showAddItemModal = true;
        },

        openEditItemModal(index) {
            if (!this.menuItems[index]) return;
            this.editingItemIndex = index;
            this.itemModalTab = 'basic';
            this.selectedImageFileName = '';
            const item = this.menuItems[index];
            this.newItem = {
                id: item.id || null,
                name: item.name || '',
                price: formatCurrencyDisplay(item.price),
                category: item.category || otherCategory,
                description: item.description || '',
                image_url: normalizeImageUrl(item.image_url),
                image_load_failed: false,
                status: item.status || 'active',
                toppings: (item.toppings || []).map(topping => ({
                    id: topping.id || null,
                    name: topping.name || '',
                    price: formatCurrencyDisplay(topping.price)
                })),
                options: (item.options || item.sizes || []).map(option => ({
                    id: option.id || null,
                    name: option.name || '',
                    price_delta: formatCurrencyDisplay(option.price_delta)
                }))
            };
            this.showAddItemModal = true;
        },

        async confirmAddItem() {
            const price = parseCleanNumber(this.newItem.price);
            if (!this.newItem.name.trim() || price < 0) return;
            this.itemSubmitting = true;
            try {
                await new Promise(resolve => window.setTimeout(resolve, 150));
                const normalizedItem = this.normalizeMenuItem({
                    ...this.newItem,
                    price: price,
                    toppings: (this.newItem.toppings || []).map(t => ({
                        ...t,
                        price: parseCleanNumber(t.price)
                    })),
                    options: (this.newItem.options || []).map(o => ({
                        ...o,
                        price_delta: parseCleanNumber(o.price_delta)
                    }))
                });
                if (this.editingItemIndex === null) {
                    this.menuItems.push(normalizedItem);
                } else {
                    this.menuItems.splice(this.editingItemIndex, 1, normalizedItem);
                }
                this.syncSelectedCategory();
                this.showAddItemModal = false;
                this.editingItemIndex = null;
            } finally {
                this.itemSubmitting = false;
            }
        },

        setMenuView(view) {
            this.menuView = view;
            if (view === 'category' && !this.menuCategories.includes(this.selectedCategory)) {
                this.selectedCategory = this.menuCategories[0] || '';
            }
        },

        updateMenuSearch(value) {
            this.menuSearchInput = value;
            window.clearTimeout(this.menuSearchTimer);
            this.menuSearchTimer = window.setTimeout(() => {
                this.menuSearch = normalizeSearch(value);
            }, 300);
        },

        get menuCategories() {
            return [...new Set(this.menuItems.map(item => item.category || otherCategory))]
                .sort((left, right) => left.localeCompare(right, 'vi'));
        },

        get availableItemCategories() {
            return [...new Set([...this.itemCategories, ...this.menuItems.map(item => item.category).filter(Boolean)])];
        },

        syncSelectedCategory() {
            if (this.menuView === 'category' && !this.menuCategories.includes(this.selectedCategory)) {
                this.selectedCategory = this.menuCategories[0] || '';
            }
        },

        categoryItemCount(category) {
            return this.menuItems.filter(item => (item.category || otherCategory) === category).length;
        },

        get visibleMenuItems() {
            return this.menuItems
                .map((item, index) => ({ item, index }))
                .filter(({ item }) => !this.menuSearch || normalizeSearch(item.name).includes(this.menuSearch))
                .filter(({ item }) => this.menuView !== 'category' || (item.category || otherCategory) === this.selectedCategory);
        },

        addTopping(item) {
            item.toppings.push({ name: '', price: '' });
        },

        addOption(item) {
            item.options.push({ name: '', price_delta: '' });
        },

        async uploadManualImage(event) {
            const file = event.target.files?.[0];
            if (!file || !imageUploadUrl) return;

            this.selectedImageFileName = file.name;

            this.imageUploading = true;
            const body = new FormData();
            body.append('image', file);

            try {
                const response = await fetch(imageUploadUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body
                });
                const payload = await response.json();
                if (!response.ok) {
                    throw new Error(payload.message || Object.values(payload.errors || {})[0] || msg('imageUploadFailed'));
                }
                this.newItem.image_url = payload.data?.url || '';
            } catch (error) {
                alert(error.message);
            } finally {
                this.imageUploading = false;
                event.target.value = '';
            }
        },

        removeMenuItem(index) {
            const item = this.menuItems[index];
            this.menuItems.splice(index, 1);
            this.syncSelectedCategory();
            if (item && window.notify) {
                window.notify(itemDeletedSuccess.replace(':name', item.name || ''), 'success');
            }
        },

        /** Remove a whole category together with every menu item that belongs to it. */
        removeMenuCategory(category) {
            const count = this.categoryItemCount(category);
            this.menuItems = this.menuItems.filter(item => (item.category || otherCategory) !== category);
            this.syncSelectedCategory();
            if (window.notify) {
                window.notify(categoryDeletedSuccess.replace(':category', category).replace(':count', String(count)), 'success');
            }
        },

        loadPreviousCampaign(campaign) {
            this.selectedPreviousCampaignId = campaign.id ?? null;
            this.form.name = campaign.name + msg('copySuffix');
            this.form.restaurant = campaign.restaurant;
            this.form.description = campaign.description || '';
            const previousMaxBudget = Number(campaign.max_budget) || 0;
            this.form.max_budget = this.campaignSettings.max_budget > 0
                ? Math.min(previousMaxBudget, this.campaignSettings.max_budget)
                : previousMaxBudget;
            this.form.flat_price = campaign.flat_price || '';
            this.form.payment_account_id = campaign.payment_account_id ? String(campaign.payment_account_id) : this.form.payment_account_id;
            this.form.sponsor_type = ['none', 'full'].includes(campaign.sponsor_type) ? campaign.sponsor_type : 'none';
            this.form.sponsor_description = campaign.sponsor_description || '';
            this.form.self_paid_price_basis = ['original', 'campaign_prorated'].includes(campaign.self_paid_price_basis) ? campaign.self_paid_price_basis : 'original';
            const previousAllocations = campaign.sponsor_allocations || [];
            const previousPercentages = wholeSponsorPercentages(previousAllocations);
            this.sponsors = previousAllocations.map((allocation, index) => ({
                user_id: String(allocation.room_user_id || ''),
                percentage: previousPercentages[index],
                search: this.roomUsers.find(user => user.id === String(allocation.room_user_id || ''))?.label || '',
                open: false
            }));
            this.applyMenuItems(campaign.items || []);
            this.menuTab = 'reuse';
        },

        loadSampleJson() {
            this.rawJson = JSON.stringify([
                {
                    category: 'Trà sữa',
                    name: 'Trà sữa trân châu đường đen',
                    price: 45000,
                    image_url: 'https://example.com/images/tra-sua-tran-chau.jpg',
                    description: 'Trà sữa kèm trân châu đường đen.',
                    toppings: [
                        { name: 'Trân châu trắng', price: 7000 },
                        { name: 'Pudding trứng', price: 10000 }
                    ],
                    options: [
                        { name: 'Size M', price_delta: 0 },
                        { name: 'Size L', price_delta: 10000 }
                    ]
                },
                {
                    category: 'Cà phê',
                    name: 'Cà phê muối',
                    price: 30000,
                    image_url: 'https://example.com/images/ca-phe-muoi.jpg',
                    toppings: [],
                    options: []
                }
            ], null, 2);
        },

        importJson() {
            try {
                const parsed = JSON.parse(this.rawJson);
                if (Array.isArray(parsed) && parsed.length > 0 && this.importableMenuItems(parsed).length > 0) {
                    this.applyMenuItems(parsed);
                    this.menuTab = 'json';
                    alert(msg('jsonLoaded', { count: this.menuItems.length }));
                } else {
                    alert(msg('jsonNotArray'));
                }
            } catch (e) {
                alert(msg('jsonInvalid', { message: e.message }));
            }
        },

        async previewCrawler() {
            if (!this.crawlerUrl) return;
            this.crawlerLoading = true;
            this.crawlerMessage = msg('crawlerConnecting', { url: this.crawlerUrl });

            try {
                const roomSlug = getRoomSlug();
                if (!roomSlug) {
                    throw new Error(msg('roomUnknown'));
                }

                const res = await fetch(`/admin/${roomSlug}/crawler/preview`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ url: this.crawlerUrl })
                });
                const response = await res.json();
                if (!res.ok) {
                    throw new Error(response.message || msg('crawlerFailed'));
                }
                const data = response.data || response;
                if (data.items && data.items.length > 0) {
                    this.applyMenuItems(data.items.map(item => ({
                        ...item,
                        category: String(item.category ?? '').trim() || crawlerMenuCategory
                    })));
                    this.form.restaurant = data.restaurant_name || this.form.restaurant || onlineRestaurantName;
                    this.menuTab = 'crawler';
                    this.crawlerMessage = msg('crawlerSuccess', { count: data.items.length });
                } else {
                    this.crawlerMessage = msg('crawlerFallback');
                }
            } catch (e) {
                this.crawlerMessage = msg('crawlerError', { message: e.message });
            } finally {
                this.crawlerLoading = false;
            }
        },

        formatCurrencyDisplay(value) {
            return formatCurrencyDisplay(value);
        },

        formatVND(num) {
            return formatMoney(num);
        },

        formatDeadline(value) {
            return formatDateTimeDisplay(value);
        },

        async submitForm(status) {
            if (!this.form.name || !this.form.restaurant) {
                alert(msg('nameRequired'));
                return;
            }

            this.submitting = true;
            if (status) {
                this.form.status = status;
            }
            if (this.form.sponsor_type === 'full') {
                const percentageTotal = this.sponsors.reduce((total, sponsor) => total + (Number(sponsor.percentage) || 0), 0);
                if (this.sponsors.length === 0 || Math.abs(percentageTotal - 100) > 0.01) {
                    alert(sponsorPercentageError);
                    this.submitting = false;
                    return;
                }
            }

            const payload = {
                name: this.form.name,
                restaurant: this.form.restaurant,
                deadline: this.form.deadline || null,
                payment_account_id: this.form.payment_account_id || null,
                description: this.form.description || null,
                sponsor_type: this.form.sponsor_type,
                sponsor_description: this.form.sponsor_description || null,
                self_paid_price_basis: this.form.self_paid_price_basis || 'original',
                max_budget: this.form.max_budget ? parseCleanNumber(this.form.max_budget) : null,
                flat_price: this.form.flat_price ? parseCleanNumber(this.form.flat_price) : null,
                sponsor_allocations: this.form.sponsor_type === 'full' ? this.sponsors.filter(sponsor => sponsor.user_id).map(sponsor => ({
                        room_user_id: Number(sponsor.user_id),
                        percentage: Number(sponsor.percentage) || 0
                    })) : [],
                status: this.form.status,
                notify_members: this.notifyMembers,
                items: this.menuItems.filter(item => item.name && item.name.trim()).map(item => ({
                    id: item.id || null,
                    name: item.name.trim(),
                    category: item.category || null,
                    description: item.description || null,
                    image_url: item.image_url || null,
                    price: parseInt(item.price, 10) || 0,
                    status: item.status || 'active',
                    toppings: (item.toppings || []).filter(topping => topping.name && topping.name.trim()).map(topping => ({
                        id: topping.id || null,
                        name: topping.name.trim(),
                        price: parseInt(topping.price, 10) || 0
                    })),
                    options: (item.options || []).filter(option => option.name && option.name.trim()).map(option => ({
                        id: option.id || null,
                        name: option.name.trim(),
                        price_delta: parseInt(option.price_delta, 10) || 0
                    }))
                }))
            };

            try {
                const res = await fetch(storeUrl, {
                    method: submitMethod,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                if (!res.ok) {
                    const err = await res.json();
                    throw new Error(err.message || Object.values(err.errors || {})[0] || (this.isEditMode ? msg('updateFailed') : msg('createFailed')));
                }

                const json = await res.json();
                const campaignId = json.data?.id || json.id;
                const effectiveStatus = status || this.form.status || 'draft';

                if (effectiveStatus === 'draft') {
                    const indexUrl = page?.dataset.indexUrl || `/admin/${getRoomSlug()}/campaigns`;
                    window.location.href = indexUrl;
                } else {
                    window.location.href = `/admin/${getRoomSlug()}/campaigns/${campaignId}/info`;
                }
            } catch (e) {
                alert(msg('genericError', { message: e.message }));
                this.showConfirmModal = false;
                this.showNotifyModal = false;
            } finally {
                this.submitting = false;
                this.submittingAction = null;
            }
        },

        saveChanges() {
            this.submittingAction = this.form.status === 'draft' ? 'draft' : 'save_changes';
            if (this.isEditMode && ['active', 'scheduled', 'closing'].includes(this.form.status)) {
                this.notifyMembers = false;
                this.showNotifyModal = true;
                return;
            }
            this.submitForm(this.form.status);
        },

        confirmSaveChanges() {
            this.showNotifyModal = false;
            this.submitForm(this.form.status);
        },

        saveChangesAndPublish() {
            this.openPublishConfirmation();
        },

        openPublishConfirmation() {
            this.pendingStatus = 'active';
            this.showConfirmModal = true;
        },

        confirmPublish() {
            this.submittingAction = 'publish';
            this.submitForm(this.pendingStatus || 'active');
        },

        saveDraft() {
            this.submittingAction = 'draft';
            this.submitForm('draft');
        },

        publishCampaign() {
            this.openPublishConfirmation();
        },

        async executeCancelCampaign() {
            const deleteUrl = page?.dataset.deleteUrl || '';
            const indexUrl = page?.dataset.indexUrl || `/admin/${getRoomSlug()}/campaigns`;
            if (!deleteUrl) {
                alert(msg('cancelUrlMissing'));
                return;
            }

            this.cancelSubmitting = true;
            try {
                const res = await fetch(deleteUrl, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    }
                });

                if (!res.ok) {
                    const err = await res.json();
                    throw new Error(err.message || Object.values(err.errors || {})[0] || msg('cancelFailed'));
                }

                window.location.href = indexUrl;
            } catch (e) {
                alert(msg('genericError', { message: e.message }));
            } finally {
                this.cancelSubmitting = false;
                this.showCancelCampaignModal = false;
            }
        }
    };
}

if (typeof window !== 'undefined') {
    window.campaignCreateComponent = campaignCreateComponent;
}
