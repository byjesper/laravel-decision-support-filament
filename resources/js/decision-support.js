// Bundle entry for the decision-support editor and runner.
//
// `npm run build` bundles this with the pinned mermaid dependency into
// resources/dist/decision-support.js, which the service provider registers as a
// Filament asset. Hosts therefore never manage the npm dependency themselves.
//
// Renders every mermaid container and re-renders after Livewire DOM morphs so the
// live editor preview and the runner's reached-path highlight stay in sync.
import mermaid from 'mermaid';

const SELECTOR = '[data-decision-support-mermaid]';

function theme() {
    const el = document.querySelector(SELECTOR);
    return (el && el.dataset.mermaidTheme) || 'default';
}

mermaid.initialize({ startOnLoad: false, theme: theme(), securityLevel: 'strict' });

// Render one container, skipping it when its source has not changed since the
// last render. This keeps repeated passes cheap (a string comparison) instead of
// re-running the (expensive) mermaid layout on every Livewire update.
async function renderContainer(container) {
    const source = container.dataset.mermaidSource ?? '';

    if (source.trim() === '' || container.__dsRenderedSource === source) {
        return;
    }

    container.__dsRenderedSource = source;

    try {
        const id = 'mermaid-' + Math.random().toString(36).slice(2);
        const { svg } = await mermaid.render(id, source);
        container.innerHTML = svg;
    } catch (error) {
        container.__dsRenderedSource = null; // allow a retry once the graph is valid
        container.innerHTML = '<pre class="text-danger-600 text-sm">' + String(error) + '</pre>';
    }
}

// Livewire's `morph.updated` hook fires once per component morph — many times per
// round-trip. Coalesce those into a single pass per animation frame so the
// preview renders at most once per update instead of dozens of times.
let scheduled = false;

function renderAll() {
    if (scheduled) {
        return;
    }

    scheduled = true;

    requestAnimationFrame(() => {
        scheduled = false;
        document.querySelectorAll(SELECTOR).forEach((container) => {
            void renderContainer(container);
        });
    });
}

function boot() {
    renderAll();

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
