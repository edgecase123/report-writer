import { ref, type Ref } from 'vue';

export const zoomLevel: Ref<number>          = ref(1.0);
export const reportUrl: Ref<string>          = ref('');
export const loading:   Ref<boolean>         = ref(false);
export const error:     Ref<string | null>   = ref(null);
export const empty:     Ref<boolean>         = ref(false);

const ZOOM_MIN = 0.5;
const ZOOM_MAX = 2.0;

export const ZOOM_PRESETS: number[] = [0.5, 0.75, 1.0, 1.25, 1.5, 1.75, 2.0];

// Presets are the source of truth for +/- (Ticket 011 Option B): snap to
// the next/previous preset rather than stepping by a fixed increment. Users
// starting from an off-preset value (e.g. programmatic zoomTo) will still
// land on a preset after one +/- press.
export function zoomIn(): void {
    const next = ZOOM_PRESETS.find((p) => p > zoomLevel.value);
    zoomLevel.value = next ?? ZOOM_MAX;
}

export function zoomOut(): void {
    let prev = ZOOM_MIN;
    for (const p of ZOOM_PRESETS) {
        if (p < zoomLevel.value) prev = p;
        else break;
    }
    zoomLevel.value = prev;
}

export function zoomReset():           void { zoomLevel.value = 1.0; }
export function zoomTo(level: number): void { zoomLevel.value = Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, +level.toFixed(2))); }
