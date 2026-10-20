{{-- Floating reminder at the bottom of the member pages (room and /me, /me/profile included) for a member who has
     not entered their birthday yet; it links to the birthday field of /me/profile. The close button hides it for one day with a
     cookie (read in the browser: the reminder is rendered hidden and only revealed while that cookie is absent). --}}
@php
    $member = auth('web')->user();
    $show = $member instanceof \App\Models\GlobalUser && $member->birthday === null;
@endphp
@if ($show)
<style>
  /* Phones: the column of floating buttons (bottom right) stays free. */
  .df-bp{position:fixed;left:12px;right:84px;bottom:16px;z-index:35;display:flex;align-items:center;gap:12px;padding:10px 10px 10px 14px;border-radius:18px;color:#fff;
    background:linear-gradient(90deg,#c026d3,#f43f5e,#f59e0b);box-shadow:0 18px 40px -16px rgba(190,24,93,.65);transform:translateY(140%);opacity:0;
    transition:transform .5s cubic-bezier(.2,.9,.3,1.2),opacity .35s}
  .df-bp[hidden]{display:none}
  .df-bp.is-visible{transform:none;opacity:1}
  @media (min-width:640px){.df-bp{left:50%;right:auto;bottom:24px;width:min(680px,calc(100vw - 180px));transform:translate(-50%,140%)}.df-bp.is-visible{transform:translate(-50%,0)}}
  .df-bp-icon{display:flex;width:36px;height:36px;flex-shrink:0;align-items:center;justify-content:center;border-radius:50%;background:rgba(255,255,255,.22);font-size:20px}
  .df-bp-icon span{display:inline-block;animation:df-bp-bob 1.6s ease-in-out infinite}
  .df-bp-text{flex:1;min-width:0;font-size:13px;font-weight:600;line-height:1.4}
  .df-bp-cta{display:inline-flex;flex-shrink:0;align-items:center;gap:4px;padding:7px 12px;border-radius:999px;background:#fff;color:#be185d;font-size:12px;font-weight:700;text-decoration:none;white-space:nowrap;transition:transform .2s}
  .df-bp-cta:hover{transform:translateY(-1px)}
  .df-bp-close{display:flex;width:30px;height:30px;flex-shrink:0;align-items:center;justify-content:center;border:0;border-radius:50%;background:rgba(0,0,0,.18);color:#fff;font-size:16px;cursor:pointer}
  .df-bp-close:hover{background:rgba(0,0,0,.3)}
  .df-bp-cta:focus-visible,.df-bp-close:focus-visible{outline:2px solid #fff;outline-offset:2px}
  @keyframes df-bp-bob{50%{transform:translateY(-3px) rotate(-8deg)}}
  @media (max-width:420px){.df-bp-cta .df-bp-cta-text{display:none}}
  @media (prefers-reduced-motion:reduce){.df-bp{transition:none}.df-bp-icon span{animation:none}}
</style>
<aside class="df-bp" data-birthday-prompt role="complementary" aria-label="{{ __('birthday.prompt_label') }}" hidden>
  <span class="df-bp-icon" aria-hidden="true"><span>🎂</span></span>
  <p class="df-bp-text">{{ __('birthday.prompt_text') }}</p>
  <a class="df-bp-cta" href="{{ route('user.me.profile') }}#contact-birthday" data-birthday-prompt-cta>
    <span class="df-bp-cta-text">{{ __('birthday.prompt_cta') }}</span><span class="material-symbols-outlined text-[16px]" aria-hidden="true">arrow_forward</span>
  </a>
  <button type="button" class="df-bp-close" data-birthday-prompt-close aria-label="{{ __('birthday.prompt_close') }}">✕</button>
</aside>
<script>
  (() => {
    const COOKIE = 'df_birthday_prompt_dismissed';
    const prompt = document.querySelector('[data-birthday-prompt]');
    // On the profile page, the link (or an arrival from it) puts the cursor in the birthday field.
    const field = document.getElementById('contact-birthday');
    const goToField = () => { field.scrollIntoView({ behavior: 'smooth', block: 'start' }); field.focus({ preventScroll: true }); };
    if (field && location.hash === '#contact-birthday') setTimeout(goToField, 50);
    if (field && prompt) {
      prompt.querySelector('[data-birthday-prompt-cta]').addEventListener('click', (event) => { event.preventDefault(); history.replaceState(null, '', '#contact-birthday'); goToField(); });
    }
    if (!prompt || document.cookie.split('; ').some((item) => item.startsWith(`${COOKIE}=`))) return;
    prompt.hidden = false;
    requestAnimationFrame(() => requestAnimationFrame(() => prompt.classList.add('is-visible')));
    prompt.querySelector('[data-birthday-prompt-close]').addEventListener('click', () => {
      // Hidden for one day on this browser.
      document.cookie = `${COOKIE}=1; max-age=86400; path=/; SameSite=Lax${location.protocol === 'https:' ? '; Secure' : ''}`;
      prompt.classList.remove('is-visible');
      setTimeout(() => { prompt.hidden = true; }, 400);
    });
  })();
</script>
@endif
