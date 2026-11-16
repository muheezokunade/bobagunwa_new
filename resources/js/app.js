// Progressive enhancement only: every form and link works without JavaScript.

document.documentElement.classList.remove('no-js');

// Confirmation for consequential actions: <form data-confirm="Message">.
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    const message = form.dataset.confirm;
    if (message && !window.confirm(message)) {
        event.preventDefault();
        return;
    }
    // Prevent accidental double submission; the server is idempotent regardless.
    const button = event.submitter;
    if (button && !form.dataset.allowResubmit) {
        window.setTimeout(() => { button.disabled = true; button.setAttribute('aria-busy', 'true'); }, 0);
    }
});

// Administration / portal navigation toggle on small screens.
document.querySelectorAll('[data-nav-toggle]').forEach((button) => {
    const nav = document.getElementById(button.getAttribute('aria-controls'));
    if (!nav) return;
    button.addEventListener('click', () => {
        const open = nav.classList.toggle('open');
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
});

// Close the public mobile menu with Escape and return focus to its toggle.
document.querySelectorAll('details.mobile-menu').forEach((menu) => {
    menu.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && menu.open) {
            menu.open = false;
            menu.querySelector('summary')?.focus();
        }
    });
});

// "Fill oldest first" helper on the allocation form (suggestion only; the
// officer still reviews and submits).
document.querySelectorAll('[data-allocation-form]').forEach((form) => {
    const button = form.querySelector('[data-fill-oldest]');
    if (!button) return;
    button.hidden = false;
    button.addEventListener('click', () => {
        let remaining = Math.round(parseFloat(form.dataset.available) * 100);
        form.querySelectorAll('[data-outstanding]').forEach((input) => {
            const outstanding = Math.round(parseFloat(input.dataset.outstanding) * 100);
            const take = Math.max(0, Math.min(outstanding, remaining));
            input.value = take > 0 ? (take / 100).toFixed(2) : '';
            remaining -= take;
        });
    });
});

// Copy buttons for one-time links.
document.querySelectorAll('[data-copy]').forEach((button) => {
    button.hidden = false;
    button.addEventListener('click', async () => {
        const target = document.getElementById(button.dataset.copy);
        try {
            await navigator.clipboard.writeText(target.textContent.trim());
            button.textContent = 'Copied';
        } catch {
            const range = document.createRange();
            range.selectNodeContents(target);
            window.getSelection().removeAllRanges();
            window.getSelection().addRange(range);
        }
    });
});

// Print buttons (CSP-safe: no inline handlers).
document.querySelectorAll('[data-print]').forEach((button) => {
    button.hidden = false;
    button.addEventListener('click', () => window.print());
});
