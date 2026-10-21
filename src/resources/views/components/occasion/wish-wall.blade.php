{{-- Wishes wall of a room event page (birthday and occasions): members write short wishes (on the birthday page, to
     one of the celebrants), give hearts to the others' wishes and delete their own. Rendered by the script below from
     the JSON wall (texts are always set with textContent) and refreshed every 30 seconds while the tab is visible.
     The room admin hides unsuitable wishes from the admin events page. --}}
@props(['room', 'type', 'wall' => [], 'celebrants' => []])
@php
    /** @var list<array{user_code: string, name: string}> $celebrants Birthday page only: celebrants a wish can be for. */
    $recipients = array_values(array_filter($celebrants, static fn (array $celebrant): bool => ($celebrant['user_code'] ?? '') !== 'DEMO'));
    $isBirthday = $type === \App\Actions\Room\SendRoomEventNotificationAction::TYPE_BIRTHDAY;
    $maxLength = \App\Services\Occasion\RoomEventWishWallService::MAX_LENGTH;
    $config = [
        'list' => route('user.event-wishes.index', [$room, $type]),
        'store' => route('user.event-wishes.store', [$room, $type]),
        'heart' => route('user.event-wishes.heart', [$room, '__ID__']),
        'destroy' => route('user.event-wishes.destroy', [$room, '__ID__']),
        'token' => csrf_token(),
        'wishes' => $wall,
        'i18n' => [
            'to' => __('occasions.wall.to', ['name' => '__NAME__']),
            'heart' => __('occasions.wall.heart'),
            'unheart' => __('occasions.wall.unheart'),
            'delete' => __('occasions.wall.delete'),
            'confirmDelete' => __('occasions.wall.confirm_delete'),
            'sent' => __('occasions.wall.sent'),
            'error' => __('occasions.wall.error'),
            'count' => __('occasions.wall.count', ['count' => '__COUNT__']),
            'mine' => __('occasions.wall.mine'),
        ],
    ];
@endphp
<style>
  .ew{position:relative;z-index:6;width:min(100% - 32px,760px);margin:0 auto 64px;padding:22px 22px 18px;border-radius:22px;color:#fff;text-align:left;
    background:rgba(15,10,30,.55);border:1px solid rgba(255,255,255,.22);box-shadow:0 24px 60px -24px rgba(0,0,0,.6);font-family:'Be Vietnam Pro',system-ui,sans-serif}
  .ew h2{display:flex;align-items:center;gap:8px;font-size:18px;font-weight:700}
  .ew h2 .ew-count{margin-left:auto;font-size:12px;font-weight:600;color:rgba(255,255,255,.7)}
  .ew-sub{margin-top:4px;font-size:13px;color:rgba(255,255,255,.75)}
  .ew-form{display:grid;gap:8px;margin-top:14px}
  .ew-form select,.ew-form textarea{width:100%;border-radius:12px;border:1px solid rgba(255,255,255,.28);background:rgba(255,255,255,.1);color:#fff;font:inherit;font-size:14px;padding:10px 12px}
  .ew-form select option{color:#1f1235}
  .ew-form textarea{min-height:76px;resize:vertical}
  .ew-form textarea::placeholder{color:rgba(255,255,255,.55)}
  .ew-form :focus-visible{outline:2px solid #fff;outline-offset:2px}
  .ew-row{display:flex;flex-wrap:wrap;align-items:center;gap:10px}
  .ew-counter{font-size:12px;color:rgba(255,255,255,.65)}
  .ew-status{flex:1;min-width:0;font-size:13px;font-weight:600}
  .ew-status.is-error{color:#fecaca}
  .ew-send{margin-left:auto;display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border:0;border-radius:999px;cursor:pointer;font:600 14px 'Be Vietnam Pro',sans-serif;color:#fff;
    background:linear-gradient(90deg,#ff3d6e,#ff5fa2);box-shadow:0 10px 24px -10px rgba(255,61,110,.8)}
  .ew-send:disabled{opacity:.6;cursor:wait}
  .ew-list{list-style:none;display:grid;gap:10px;margin-top:16px;max-height:520px;overflow-y:auto;padding-right:4px}
  .ew-item{display:flex;gap:12px;padding:12px 14px;border-radius:16px;background:rgba(255,255,255,.09);border:1px solid rgba(255,255,255,.14)}
  .ew-item.is-mine{border-color:rgba(255,201,77,.55)}
  .ew-avatar{position:relative;flex:none;width:38px;height:38px;border-radius:50%;overflow:hidden;display:grid;place-items:center;font-weight:700;background:linear-gradient(135deg,#ff5fa2,#8b5cf6)}
  .ew-avatar img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
  .ew-body{flex:1;min-width:0}
  .ew-meta{display:flex;flex-wrap:wrap;align-items:baseline;gap:4px 8px;font-size:12px;color:rgba(255,255,255,.7)}
  .ew-meta strong{font-size:14px;color:#fff}
  .ew-text{margin-top:4px;font-size:14px;line-height:1.55;white-space:pre-line;overflow-wrap:anywhere}
  .ew-actions{display:flex;align-items:center;gap:8px;margin-top:8px}
  .ew-actions button{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:999px;border:1px solid rgba(255,255,255,.25);background:rgba(255,255,255,.08);color:#fff;font:600 12px 'Be Vietnam Pro',sans-serif;cursor:pointer}
  .ew-actions button[aria-pressed="true"]{background:rgba(255,61,110,.35);border-color:rgba(255,95,162,.8)}
  .ew-actions button:focus-visible{outline:2px solid #fff;outline-offset:2px}
  .ew-actions .ew-delete{margin-left:auto;color:rgba(255,255,255,.75)}
  .ew-actions .ew-delete.is-confirming{background:rgba(220,38,38,.55);color:#fff}
  .ew-empty{margin-top:14px;font-size:13px;color:rgba(255,255,255,.7);text-align:center}
</style>
<section class="ew" id="event-wish-wall" aria-labelledby="event-wish-wall-title" data-wish-wall data-config="{{ json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}">
  <h2 id="event-wish-wall-title"><span aria-hidden="true">💬</span> {{ __('occasions.wall.title') }} <span class="ew-count" data-wall-count></span></h2>
  <p class="ew-sub">{{ __($isBirthday ? 'occasions.wall.subtitle_birthday' : 'occasions.wall.subtitle') }}</p>

  <form class="ew-form" data-wall-form novalidate>
    @if ($isBirthday)
      @if (count($recipients) > 1)
        <label>
          <span class="sr-only" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)">{{ __('occasions.wall.recipient') }}</span>
          <select name="recipient" required>
            @foreach ($recipients as $celebrant)
              <option value="{{ $celebrant['user_code'] }}">{{ __('occasions.wall.to', ['name' => $celebrant['name']]) }}</option>
            @endforeach
          </select>
        </label>
      @elseif (count($recipients) === 1)
        <input type="hidden" name="recipient" value="{{ $recipients[0]['user_code'] }}">
      @endif
    @endif
    <label>
      <span class="sr-only" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)">{{ __('occasions.wall.message') }}</span>
      <textarea name="message" maxlength="{{ $maxLength }}" required placeholder="{{ __('occasions.wall.placeholder') }}" data-wall-message></textarea>
    </label>
    <div class="ew-row">
      <span class="ew-counter" data-wall-counter aria-hidden="true">0 / {{ $maxLength }}</span>
      <span class="ew-status" role="status" aria-live="polite" data-wall-status></span>
      <button type="submit" class="ew-send" data-wall-send>💌 {{ __('occasions.wall.send') }}</button>
    </div>
  </form>

  <ul class="ew-list" data-wall-list aria-label="{{ __('occasions.wall.title') }}"></ul>
  <p class="ew-empty" data-wall-empty hidden>{{ __('occasions.wall.empty') }}</p>
</section>
<script>
  (() => {
    const wall = document.querySelector('[data-wish-wall]');
    if (!wall) return;
    let config = {};
    try { config = JSON.parse(wall.dataset.config || '{}'); } catch { config = {}; }
    const t = config.i18n || {};
    const list = wall.querySelector('[data-wall-list]');
    const empty = wall.querySelector('[data-wall-empty]');
    const count = wall.querySelector('[data-wall-count]');
    const form = wall.querySelector('[data-wall-form]');
    const message = wall.querySelector('[data-wall-message]');
    const counter = wall.querySelector('[data-wall-counter]');
    const status = wall.querySelector('[data-wall-status]');
    const send = wall.querySelector('[data-wall-send]');
    const max = Number(message.maxLength) || 280;

    const request = async (url, options = {}) => {
      const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': config.token, ...(options.headers || {}) },
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) {
        const first = data.errors ? (Object.values(data.errors)[0] || [])[0] : null;
        throw new Error(first || data.message || t.error);
      }
      return data;
    };
    const setStatus = (text, error = false) => {
      status.textContent = text;
      status.classList.toggle('is-error', error);
    };
    const el = (tag, className, text) => {
      const node = document.createElement(tag);
      if (className) node.className = className;
      if (text !== undefined) node.textContent = text;
      return node;
    };

    const heartButton = (wish) => {
      const button = el('button', 'ew-heart');
      button.type = 'button';
      button.setAttribute('aria-pressed', wish.hearted ? 'true' : 'false');
      button.setAttribute('aria-label', `${wish.hearted ? t.unheart : t.heart} (${wish.hearts})`);
      button.append(el('span', '', wish.hearted ? '❤️' : '🤍'), el('span', '', String(wish.hearts)));
      button.addEventListener('click', async () => {
        button.disabled = true;
        try {
          const data = await request(config.heart.replace('__ID__', wish.id), { method: 'POST' });
          Object.assign(wish, { hearted: data.hearted, hearts: data.hearts });
          button.replaceWith(heartButton(wish));
          if (data.hearted) window.EventAudio?.play('chime');
        } catch (error) {
          setStatus(error.message, true);
          button.disabled = false;
        }
      });
      return button;
    };

    const deleteButton = (wish) => {
      const button = el('button', 'ew-delete', t.delete);
      button.type = 'button';
      let timer = null;
      // Two-step delete (no browser dialog): the first click asks, the second within 4 seconds deletes.
      button.addEventListener('click', async () => {
        if (!button.classList.contains('is-confirming')) {
          button.classList.add('is-confirming');
          button.textContent = t.confirmDelete;
          timer = setTimeout(() => { button.classList.remove('is-confirming'); button.textContent = t.delete; }, 4000);
          return;
        }
        clearTimeout(timer);
        button.disabled = true;
        try {
          await request(config.destroy.replace('__ID__', wish.id), { method: 'DELETE' });
          config.wishes = config.wishes.filter((item) => item.id !== wish.id);
          render();
        } catch (error) {
          setStatus(error.message, true);
          button.disabled = false;
        }
      });
      return button;
    };

    const item = (wish) => {
      const li = el('li', 'ew-item' + (wish.mine ? ' is-mine' : ''));
      const avatar = el('span', 'ew-avatar', (wish.author || '?').trim().charAt(0).toUpperCase());
      avatar.setAttribute('aria-hidden', 'true');
      if (wish.avatar_url) {
        const img = el('img');
        img.src = wish.avatar_url;
        img.alt = '';
        img.loading = 'lazy';
        img.addEventListener('error', () => img.remove(), { once: true });
        avatar.append(img);
      }
      const body = el('div', 'ew-body');
      const meta = el('div', 'ew-meta');
      meta.append(el('strong', '', wish.author));
      if (wish.recipient) meta.append(el('span', '', t.to.replace('__NAME__', wish.recipient)));
      if (wish.mine) meta.append(el('span', '', `· ${t.mine}`));
      meta.append(el('span', '', `· ${wish.time}`));
      const actions = el('div', 'ew-actions');
      actions.append(heartButton(wish));
      if (wish.mine) actions.append(deleteButton(wish));
      body.append(meta, el('p', 'ew-text', wish.message), actions);
      li.append(avatar, body);
      return li;
    };

    const render = () => {
      const wishes = Array.isArray(config.wishes) ? config.wishes : [];
      list.replaceChildren(...wishes.map(item));
      empty.hidden = wishes.length > 0;
      count.textContent = wishes.length > 0 ? t.count.replace('__COUNT__', wishes.length) : '';
    };

    message.addEventListener('input', () => { counter.textContent = `${message.value.length} / ${max}`; });
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (message.value.trim() === '') { message.focus(); return; }
      send.disabled = true;
      setStatus('');
      try {
        const data = await request(config.store, { method: 'POST', body: new FormData(form) });
        config.wishes = data.wishes || [];
        render();
        form.reset();
        counter.textContent = `0 / ${max}`;
        setStatus('✓ ' + (data.message || t.sent));
        window.EventAudio?.play('chime');
      } catch (error) {
        setStatus(error.message, true);
      } finally {
        send.disabled = false;
      }
    });

    // Wishes of the others appear without reloading the page.
    setInterval(async () => {
      if (document.hidden || list.querySelector('.is-confirming')) return;
      try {
        const data = await request(config.list);
        config.wishes = data.wishes || [];
        render();
      } catch { /* the next refresh will try again */ }
    }, 30000);

    render();
  })();
</script>
