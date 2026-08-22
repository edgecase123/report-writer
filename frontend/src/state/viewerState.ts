import { ref, type Ref } from 'vue';

export const zoomLevel: Ref<number>          = ref(1.0);
export const reportUrl: Ref<string>          = ref('');
export const loading:   Ref<boolean>         = ref(false);
export const error:     Ref<string | null>   = ref(null);
export const empty:     Ref<boolean>         = ref(false);

const ZOOM_STEP = 0.25;
const ZOOM_MIN  = 0.5;
const ZOOM_MAX  = 2.0;

export const ZOOM_PRESETS: number[] = [0.5, 0.75, 1.0, 1.25, 1.5, 1.75, 2.0];

export function zoomIn():              void { zoomLevel.value = Math.min(ZOOM_MAX, +(zoomLevel.value + ZOOM_STEP).toFixed(2)); }
export function zoomOut():             void { zoomLevel.value = Math.max(ZOOM_MIN, +(zoomLevel.value - ZOOM_STEP).toFixed(2)); }
export function zoomReset():           void { zoomLevel.value = 1.0; }
export function zoomTo(level: number): void { zoomLevel.value = Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, +level.toFixed(2))); }
