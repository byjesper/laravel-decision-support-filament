// Prebuilt decision-support diagram loader.
//
// This checked-in build keeps the package usable without a Node toolchain by
// importing mermaid from a pinned ESM CDN at runtime. Running `npm run build`
// (see package.json) overwrites this file with a fully self-contained, offline
// bundle of resources/js/decision-support.js for production hosts that prefer
// to vendor the dependency.
const MERMAID_URL = 'https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.esm.min.mjs';
const SELECTOR = '[data-decision-support-mermaid]';

let mermaidPromise = null;

function loadMermaid() {
    mermaidPromise ??= import(/* @vite-ignore */ MERMAID_URL).then((module) => module.default);

    return mermaidPromise;
}

function theme() {
    const el = document.querySelector(SELECTOR);
    return (el && el.dataset.mermaidTheme) || 'default';
}

async function renderAll() {
    const containers = document.querySelectorAll(SELECTOR);
    if (containers.length === 0) {
        return;
    }

    const mermaid = await loadMermaid();
    mermaid.initialize({ startOnLoad: false, theme: theme(), securityLevel: 'strict' });

    for (const container of containers) {
        const source = container.dataset.mermaidSource ?? container.textContent ?? '';
        if (source.trim() === '') {
            continue;
        }

        try {
            const id = 'mermaid-' + Math.random().toString(36).slice(2);
            const { svg } = await mermaid.render(id, source);
            container.innerHTML = svg;
        } catch (error) {
            container.innerHTML = '<pre class="text-danger-600 text-sm">' + String(error) + '</pre>';
        }
    }
}

function boot() {
    renderAll();

    document.addEventListener('livewire:initialized', () => {
        if (window.Livewire && typeof window.Livewire.hook === 'function') {
            window.Livewire.hook('morph.updated', () => renderAll());
        }
    });

    document.addEventListener('livewire:navigated', () => renderAll());
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}

export { renderAll };
