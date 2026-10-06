/**
 * Admin Authentication & Security Helpers (Password toggle & Captcha Refresh)
 */
export function initAdminAuth() {
    const passwordInput = document.querySelector('#admin-password');
    const passwordToggle = document.querySelector('#toggle-admin-password');
    if (passwordInput && passwordToggle && !passwordToggle.dataset.bound) {
        passwordToggle.dataset.bound = 'true';
        passwordToggle.addEventListener('click', (e) => {
            e.preventDefault();
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            const icon = passwordToggle.querySelector('.material-symbols-outlined');
            if (icon) {
                icon.textContent = isPassword ? 'visibility_off' : 'visibility';
            }
        });
    }

    const confirmPasswordInput = document.querySelector('#admin-password-confirmation');
    const confirmPasswordToggle = document.querySelector('#toggle-admin-password-confirmation');
    if (confirmPasswordInput && confirmPasswordToggle && !confirmPasswordToggle.dataset.bound) {
        confirmPasswordToggle.dataset.bound = 'true';
        confirmPasswordToggle.addEventListener('click', (e) => {
            e.preventDefault();
            const isPassword = confirmPasswordInput.type === 'password';
            confirmPasswordInput.type = isPassword ? 'text' : 'password';
            const icon = confirmPasswordToggle.querySelector('.material-symbols-outlined');
            if (icon) {
                icon.textContent = isPassword ? 'visibility_off' : 'visibility';
            }
        });
    }

    // Live password checklist: <ul data-password-rules data-password="#…" data-confirmation="#…"> whose
    // <li data-rule="length|match"> get data-met="true" once the rule holds (styling is done in the markup).
    document.querySelectorAll('[data-password-rules]').forEach((list) => {
        if (list.dataset.bound) return;
        const password = document.querySelector(list.dataset.password || '');
        const confirmation = document.querySelector(list.dataset.confirmation || '');
        if (!password) return;
        list.dataset.bound = 'true';
        const minLength = Number(password.getAttribute('minlength')) || 8;
        const checks = {
            length: () => password.value.length >= minLength,
            match: () => confirmation !== null && password.value !== '' && password.value === confirmation.value,
        };
        const refresh = () => {
            list.querySelectorAll('[data-rule]').forEach((item) => {
                const check = checks[item.dataset.rule];
                item.dataset.met = check && check() ? 'true' : 'false';
            });
        };
        password.addEventListener('input', refresh);
        confirmation?.addEventListener('input', refresh);
        refresh();
    });

    // Captcha Refresh Handler (matching Feedback / Contact captcha)
    const refreshBtn = document.getElementById('refresh-captcha-btn');
    const captchaWrapper = document.getElementById('captcha-img-wrapper');

    async function refreshCaptcha(e) {
        if (e) e.preventDefault();
        const wrapper = document.getElementById('captcha-img-wrapper');
        const btn = document.getElementById('refresh-captcha-btn');
        if (!wrapper) return;

        const syncIcon = btn ? btn.querySelector('span.material-symbols-outlined') : null;
        if (syncIcon) syncIcon.classList.add('animate-spin');

        const loadingText = wrapper.dataset.loadingText || '';
        const captchaApiUrl = wrapper.dataset.captchaApi || '/captcha/api/contact';
        const captchaFallbackUrl = wrapper.dataset.captchaFallback || '/captcha/contact';

        wrapper.innerHTML = `
            <div class="flex items-center gap-1.5 text-xs text-primary font-medium animate-pulse">
                <svg class="animate-spin h-3.5 w-3.5 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>${loadingText}</span>
            </div>
        `;

        try {
            const res = await fetch(captchaApiUrl + '?' + Date.now(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (res.ok) {
                const data = await res.json();
                wrapper.innerHTML = '';
                if (data.img) {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = data.img;
                    const newImg = tempDiv.querySelector('img');
                    if (newImg) {
                        newImg.className = 'w-full h-full object-contain';
                        newImg.alt = 'captcha';
                        wrapper.appendChild(newImg);
                    } else {
                        const directImg = document.createElement('img');
                        directImg.src = captchaFallbackUrl + '?' + Date.now();
                        directImg.alt = 'captcha';
                        directImg.className = 'w-full h-full object-contain';
                        wrapper.appendChild(directImg);
                    }
                } else {
                    const directImg = document.createElement('img');
                    directImg.src = captchaFallbackUrl + '?' + Date.now();
                    directImg.alt = 'captcha';
                    directImg.className = 'w-full h-full object-contain';
                    wrapper.appendChild(directImg);
                }
            } else {
                wrapper.innerHTML = '';
                const directImg = document.createElement('img');
                directImg.src = captchaFallbackUrl + '?' + Date.now();
                directImg.alt = 'captcha';
                directImg.className = 'w-full h-full object-contain';
                wrapper.appendChild(directImg);
            }
        } catch (err) {
            wrapper.innerHTML = '';
            const directImg = document.createElement('img');
            directImg.src = captchaFallbackUrl + '?' + Date.now();
            directImg.alt = 'captcha';
            directImg.className = 'w-full h-full object-contain';
            wrapper.appendChild(directImg);
        } finally {
            if (syncIcon) {
                setTimeout(() => {
                    syncIcon.classList.remove('animate-spin');
                }, 300);
            }
        }
    }

    if (refreshBtn) {
        refreshBtn.addEventListener('click', refreshCaptcha);
    }
    if (captchaWrapper) {
        captchaWrapper.addEventListener('click', refreshCaptcha);
    }
}

