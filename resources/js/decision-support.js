// Bundle entry for the decision-support editor and runner.
//
// `npm run build` bundles this with the pinned mermaid dependency into
// resources/dist/decision-support.js, which the service provider registers as a
// Filament asset. Hosts therefore never manage the npm dependency themselves.
//
// Render every mermaid container once on load, and re-render after each
// Livewire DOM morph so the live editor preview and the runner's reached-path
// highlight stay in sync.
import mermaid from 'mermaid';

const SELECTOR = '[data-decision-support-mermaid]';

function theme() {
    const el = document.querySelector(SELECTOR);
    return (el && el.dataset.mermaidTheme) || 'default';
}

mermaid.initialize({ startOnLoad: false, theme: theme(), securityLevel: 'strict' });

async function renderAll() {
    const containers = document.querySelectorAll(SELECTOR);

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

    // Re-render after Livewire updates the DOM (node/edge edits, run advances).
    document.addEventListener('livewire:initialized', () => {
        if (window.Livewire && typeof window.Livewire.hook === 'function') {
            window.Livewire.hook('morph.updated', () => renderAll());
        }
    });

    // SPA navigation between panel pages.
    document.addEventListener('livewire:navigated', () => renderAll());
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}

export { renderAll };
