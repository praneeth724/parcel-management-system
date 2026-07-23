import './bootstrap';

import * as bootstrap from 'bootstrap';
import Chart from 'chart.js/auto';
import SignaturePad from 'signature_pad';

// Exposed for the inline Blade snippets that build a chart, open a modal, or
// capture a receiver's signature. Bundling here rather than importing inside a
// Blade <script type="module"> — a bare specifier like 'signature_pad' cannot
// be resolved by the browser at runtime.
window.bootstrap = bootstrap;
window.Chart = Chart;
window.SignaturePad = SignaturePad;

// Shared Chart.js defaults so every chart in the app looks like it belongs to
// the same system rather than to whichever page drew it.
Chart.defaults.font.family =
    "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
Chart.defaults.font.size = 12;
Chart.defaults.color = '#64748b';
Chart.defaults.plugins.legend.labels.usePointStyle = true;
Chart.defaults.plugins.legend.labels.boxWidth = 8;
Chart.defaults.plugins.tooltip.padding = 10;
Chart.defaults.plugins.tooltip.cornerRadius = 6;
Chart.defaults.maintainAspectRatio = false;

document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    initTooltips();
    initAutoDismissAlerts();
    initConfirmActions();
    initAutoSubmitFilters();
    initImagePreviews();
    initPasswordToggles();
});

/**
 * Off-canvas sidebar on tablet and mobile.
 */
function initSidebar() {
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const backdrop = document.querySelector('.sidebar-backdrop');

    const close = () => document.body.classList.remove('sidebar-open');

    toggle?.addEventListener('click', () => document.body.classList.toggle('sidebar-open'));
    backdrop?.addEventListener('click', close);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') close();
    });
}

function initTooltips() {
    document
        .querySelectorAll('[data-bs-toggle="tooltip"]')
        .forEach((el) => new bootstrap.Tooltip(el));
}

/**
 * Flash messages fade out on their own; validation errors stay put.
 */
function initAutoDismissAlerts() {
    document.querySelectorAll('.alert[data-auto-dismiss]').forEach((el) => {
        setTimeout(() => bootstrap.Alert.getOrCreateInstance(el).close(), 6000);
    });
}

/**
 * Any form or link marked `data-confirm="..."` asks before it fires. Used for
 * deletes, cancellations and anything else that cannot be undone with a click.
 */
function initConfirmActions() {
    document.addEventListener('submit', (e) => {
        const message = e.target.dataset?.confirm;
        if (message && !window.confirm(message)) {
            e.preventDefault();
        }
    });

    document.addEventListener('click', (e) => {
        const link = e.target.closest('a[data-confirm]');
        if (link && !window.confirm(link.dataset.confirm)) {
            e.preventDefault();
        }
    });
}

/**
 * Filter dropdowns submit their form on change, so a filter never needs a
 * separate "Apply" click.
 */
function initAutoSubmitFilters() {
    document.querySelectorAll('[data-auto-submit]').forEach((el) => {
        el.addEventListener('change', () => el.form?.submit());
    });
}

/**
 * Live thumbnail preview for file inputs, so the user sees what they picked
 * before submitting.
 */
function initImagePreviews() {
    document.querySelectorAll('input[type="file"][data-preview]').forEach((input) => {
        const target = document.querySelector(input.dataset.preview);
        if (!target) return;

        input.addEventListener('change', () => {
            const [file] = input.files ?? [];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = (e) => {
                target.src = e.target.result;
                target.classList.remove('d-none');
            };
            reader.readAsDataURL(file);
        });
    });
}

/**
 * Show/hide toggle on password fields.
 */
function initPasswordToggles() {
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const input = document.querySelector(button.dataset.passwordToggle);
        if (!input) return;

        button.addEventListener('click', () => {
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            const icon = button.querySelector('i');
            icon?.classList.toggle('bi-eye', !isPassword);
            icon?.classList.toggle('bi-eye-slash', isPassword);
        });
    });
}
