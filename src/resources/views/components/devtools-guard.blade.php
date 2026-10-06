{{-- Production-only deterrent on user-facing pages: blocks the context menu and the usual DevTools / view-source
     shortcuts. It does not protect data (anything sent to the browser stays inspectable); real protection is server-side. --}}
@production
<script>
    (() => {
        const blocked = (event) => {
            const key = (event.key || '').toLowerCase();
            const mod = event.ctrlKey || event.metaKey;
            if (key === 'f12') return true;
            // Ctrl+Shift+I/J/C/K (Chrome/Firefox DevTools panels) and Cmd+Option+I/J/C/U on macOS.
            if (mod && (event.shiftKey || event.altKey) && ['i', 'j', 'c', 'k', 'u'].includes(key)) return true;
            // Ctrl+U (view source) and Ctrl+S (save page).
            return mod && !event.shiftKey && !event.altKey && ['u', 's'].includes(key);
        };
        document.addEventListener('contextmenu', (event) => event.preventDefault());
        document.addEventListener('keydown', (event) => {
            if (!blocked(event)) return;
            event.preventDefault();
            event.stopPropagation();
        }, true);
    })();
</script>
@endproduction
