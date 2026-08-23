<template>
    <div
        ref="canvasEl"
        class="viewer-canvas"
        :class="{ 'viewer-canvas--pannable': hasOverflow && !isDragging, 'viewer-canvas--dragging': isDragging }"
        @pointerdown="onPointerDown"
        @pointermove="onPointerMove"
        @pointerup="onPointerUp"
        @pointercancel="onPointerUp"
    >
        <div v-if="loading" class="viewer-canvas__state" role="status" aria-live="polite">Loading report…</div>
        <div v-else-if="empty" class="viewer-canvas__state viewer-canvas__state--empty" role="status">
            No report URL provided.
        </div>
        <div v-else-if="error" class="viewer-canvas__state viewer-canvas__state--error" role="alert" aria-live="assertive">
            <div class="viewer-canvas__error-msg">{{ error }}</div>
            <button class="viewer-canvas__retry" @click="load" aria-label="Retry loading report">Retry</button>
        </div>
        <div
            v-else
            class="report-scaler"
            :style="{ width: `${basePageWidth * zoomLevel}pt`, height: `${basePageHeight * zoomLevel}pt` }"
        >
            <main
                class="report-inner"
                :style="{ transform: `scale(${zoomLevel})`, transformOrigin: 'top left' }"
                aria-label="Report content"
                v-html="reportHtml"
            ></main>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import { zoomLevel, reportUrl, loading, error, empty } from '../state/viewerState';

// US Letter default (612 x 792 pt) — matches PageConfig default on the server.
const DEFAULT_PAGE_WIDTH  = 612;
const DEFAULT_PAGE_HEIGHT = 792;

const reportHtml     = ref<string>('');
const basePageWidth  = ref<number>(DEFAULT_PAGE_WIDTH);
const basePageHeight = ref<number>(DEFAULT_PAGE_HEIGHT);
const canvasEl       = ref<HTMLElement | null>(null);
const hasOverflow    = ref<boolean>(false);
const isDragging     = ref<boolean>(false);

/**
 * Parse a CSSStyleDeclaration length string like "612pt" or "792.00pt" to a
 * number of points. Returns null if the value is missing or not in pt units,
 * so callers can fall back to a default.
 */
function parsePtLength(value: string): number | null {
    if (!value) {
        return null;
    }
    const match = value.trim().match(/^([0-9]+(?:\.[0-9]+)?)pt$/);
    if (!match) {
        return null;
    }
    return parseFloat(match[1]);
}

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

        // Extract base page dimensions from the first .rw-page element's inline
        // style. HtmlRenderer::renderPage emits e.g. style="width:612.00pt;height:792.00pt;".
        // These dimensions size the .report-scaler wrapper so the scroll
        // container reserves the correct horizontal space at any zoom level
        // (Ticket 007 — reserved-width wrapper approach).
        const firstPage = doc.querySelector('.rw-page') as HTMLElement | null;
        if (firstPage) {
            const w = parsePtLength(firstPage.style.width);
            const h = parsePtLength(firstPage.style.height);
            basePageWidth.value  = w ?? DEFAULT_PAGE_WIDTH;
            basePageHeight.value = h ?? DEFAULT_PAGE_HEIGHT;
            if (w === null || h === null) {
                console.warn(
                    '[ReportCanvas] .rw-page found but width/height style unparsable; falling back to US Letter (612x792).'
                );
            }
        } else {
            basePageWidth.value  = DEFAULT_PAGE_WIDTH;
            basePageHeight.value = DEFAULT_PAGE_HEIGHT;
            console.warn(
                '[ReportCanvas] No .rw-page element in report HTML; falling back to US Letter (612x792) for scaler dimensions.'
            );
        }

        reportHtml.value = styleBlocks + doc.body.innerHTML;
    } catch (e) {
        error.value = `Failed to load report: ${e instanceof Error ? e.message : String(e)}`;
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    load();
    window.addEventListener('resize', updateOverflow);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', updateOverflow);
});

function updateOverflow(): void {
    const el = canvasEl.value;
    if (!el) {
        hasOverflow.value = false;
        return;
    }
    hasOverflow.value = el.scrollWidth > el.clientWidth || el.scrollHeight > el.clientHeight;
}

// Ticket 019 (Option A): after any zoom change, re-center the report
// horizontally in the scroll container and reset vertical scroll to top.
// nextTick waits for the .report-scaler width to update before we read
// scrollWidth. Vertical resets to top rather than preserving position —
// simplest choice; upgrade to focal-point preservation later if needed.
watch(zoomLevel, async () => {
    await nextTick();
    const el = canvasEl.value;
    if (!el) return;
    el.scrollLeft = Math.max(0, (el.scrollWidth - el.clientWidth) / 2);
    el.scrollTop  = 0;
    updateOverflow();
});

// Report content arrives async; recompute overflow once it's rendered so
// the grab cursor appears on the first paint that has real dimensions.
watch(reportHtml, async () => {
    await nextTick();
    updateOverflow();
});

// Ticket 020: drag-to-pan when the scaled report overflows the viewport.
// Skip panning when the pointer landed on a text-bearing element so native
// text selection still works (Option (a) from the ticket); see isTextTarget.
let startX = 0;
let startY = 0;
let startScrollLeft = 0;
let startScrollTop  = 0;
let activePointerId: number | null = null;

function isTextTarget(target: EventTarget | null): boolean {
    // Report HTML from HtmlRenderer places each text value in its own leaf
    // <div class="rw-el ..."> whose direct child is a Text node. Container
    // elements (.viewer-canvas, .report-scaler, .report-inner, .rw-page) have
    // element children only. So "clicked on text" ≡ "target has a direct
    // TEXT_NODE child with non-whitespace content." caretPositionFromPoint
    // was tried first but snaps to the nearest text even for background
    // clicks, so it hijacked pan on the padding.
    if (!(target instanceof Element)) return false;
    return Array.from(target.childNodes).some(
        (child) => child.nodeType === Node.TEXT_NODE && !!child.nodeValue && child.nodeValue.trim() !== ''
    );
}

function onPointerDown(e: PointerEvent): void {
    if (e.button !== 0) return;             // primary button only
    if (!hasOverflow.value) return;         // nothing to pan
    if (isTextTarget(e.target)) return;

    const el = canvasEl.value;
    if (!el) return;

    activePointerId = e.pointerId;
    startX          = e.clientX;
    startY          = e.clientY;
    startScrollLeft = el.scrollLeft;
    startScrollTop  = el.scrollTop;
    isDragging.value = true;
    el.setPointerCapture(e.pointerId);
    e.preventDefault();
}

function onPointerMove(e: PointerEvent): void {
    if (activePointerId === null || e.pointerId !== activePointerId) return;
    const el = canvasEl.value;
    if (!el) return;
    el.scrollLeft = startScrollLeft - (e.clientX - startX);
    el.scrollTop  = startScrollTop  - (e.clientY - startY);
}

function onPointerUp(e: PointerEvent): void {
    if (activePointerId === null || e.pointerId !== activePointerId) return;
    canvasEl.value?.releasePointerCapture(e.pointerId);
    activePointerId  = null;
    isDragging.value = false;
}
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

/*
 * Ticket 020: drag-to-pan cursor states. Only show grab affordance when the
 * scaled report actually overflows (hasOverflow is JS-computed and toggles
 * the --pannable class). During an active drag, --dragging overrides to
 * grabbing regardless of overflow (isDragging implies overflow anyway).
 */
.viewer-canvas--pannable {
    cursor: grab;
}

.viewer-canvas--dragging {
    cursor: grabbing;
    user-select: none;
}

/*
 * Reserved-width wrapper (Ticket 007).
 *
 * The child .report-inner uses `transform: scale(zoomLevel)` from a top-left
 * origin. Because CSS transforms don't affect layout size, we need this wrapper
 * to reserve the post-scale footprint in the scroll container. Without it, at
 * zoom > 100% the report visually overflows but the scroll container thinks
 * the content is only its natural (unscaled) size — combined with
 * `align-items: center` on .viewer-canvas the overflowing left edge became
 * unreachable via horizontal scroll.
 *
 * Flex parent's align-items: center centers this wrapper when it's narrower
 * than the viewport (zoom <= 100%), preserving the centered feel at the
 * common case.
 */
.report-scaler {
    position: relative;
    flex-shrink: 0;
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
    /*
     * In print, the scale transform is suppressed by the global @media print
     * rule in App.vue (transform: none !important on .report-inner). The
     * scaler wrapper still has explicit width/height inline, but browsers
     * paginate on the actual report content and @page rules — collapse the
     * wrapper so it can't create phantom whitespace or spurious page breaks.
     */
    .report-scaler {
        width: auto !important;
        height: auto !important;
    }
}
</style>
