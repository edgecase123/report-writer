<template>
    <div class="viewer-canvas">
        <div v-if="loading" class="viewer-canvas__state" role="status" aria-live="polite">Loading report…</div>
        <div v-else-if="empty" class="viewer-canvas__state viewer-canvas__state--empty" role="status">
            No report URL provided.
        </div>
        <div v-else-if="error" class="viewer-canvas__state viewer-canvas__state--error" role="alert" aria-live="assertive">
            <div class="viewer-canvas__error-msg">{{ error }}</div>
            <button class="viewer-canvas__retry" @click="load" aria-label="Retry loading report">Retry</button>
        </div>
        <main
            v-else
            class="report-inner"
            :style="{ transform: `scale(${zoomLevel})`, transformOrigin: 'top center' }"
            aria-label="Report content"
            v-html="reportHtml"
        ></main>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { zoomLevel, reportUrl, loading, error, empty } from '../state/viewerState';

const reportHtml = ref<string>('');

async function load(): Promise<void> {
    if (!reportUrl.value) {
        empty.value   = true;
        error.value   = null;
        loading.value = false;
        return;
    }

    empty.value   = false;
    loading.value = true;
    error.value   = null;

    try {
        const response = await fetch(reportUrl.value, { credentials: 'include' });

        if (!response.ok) {
            throw new Error(`Server returned ${response.status}`);
        }

        const html = await response.text();

        // Parse the server-generated report document via DOMParser (not regex).
        // Injected HTML is server-generated and trusted (authenticated endpoint) —
        // the server escapes every value via HtmlRenderer::htmlspecialchars.
        const doc = new DOMParser().parseFromString(html, 'text/html');
        if (!doc.body) {
            throw new Error('Malformed report response — no <body> element.');
        }
        const styleBlocks = Array.from(doc.head.querySelectorAll('style'))
            .map((s) => s.outerHTML)
            .join('\n');
        reportHtml.value = styleBlocks + doc.body.innerHTML;
    } catch (e) {
        error.value = `Failed to load report: ${e instanceof Error ? e.message : String(e)}`;
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<style scoped>
.viewer-canvas {
    flex: 1;
    overflow: auto;
    background: #e0e0e0;
    padding: 24px;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.viewer-canvas__state {
    padding: 48px;
    font-family: sans-serif;
    font-size: 16px;
    color: #555;
    align-self: center;
}

.viewer-canvas__state--empty {
    color: #555;
}

.viewer-canvas__state--error {
    color: #c0392b;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
}

.viewer-canvas__retry {
    padding: 6px 16px;
    font-size: 14px;
    background: #c0392b;
    color: #fff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.viewer-canvas__retry:hover {
    background: #a5321f;
}

.viewer-canvas__retry:focus-visible {
    outline: 2px solid #6cb6ff;
    outline-offset: 2px;
}

@media print {
    .viewer-canvas__state {
        display: none !important;
    }
}
</style>
