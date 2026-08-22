<template>
    <div class="viewer-toolbar" role="toolbar" aria-label="Report viewer controls">
        <div class="viewer-toolbar__controls">
            <button @click="zoomOut" :disabled="zoomLevel <= 0.5" aria-label="Zoom out" title="Zoom out">−</button>
            <select :value="selectValue" @change="onSelectChange" aria-label="Zoom level" title="Zoom preset">
                <option v-if="selectValue === ''" value="" disabled>{{ Math.round(zoomLevel * 100) }}%</option>
                <option v-for="p in ZOOM_PRESETS" :key="p" :value="p">{{ Math.round(p * 100) }}%</option>
            </select>
            <button @click="zoomIn" :disabled="zoomLevel >= 2.0" aria-label="Zoom in" title="Zoom in">+</button>
            <button @click="zoomReset" aria-label="Reset zoom to 100%" title="Reset to 100%">Reset</button>
        </div>
        <div class="viewer-toolbar__actions">
            <button @click="print" aria-label="Print report" title="Print">Print</button>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { zoomLevel, zoomIn, zoomOut, zoomReset, zoomTo, ZOOM_PRESETS } from '../state/viewerState';

const selectValue = computed<number | ''>(() =>
    ZOOM_PRESETS.includes(zoomLevel.value) ? zoomLevel.value : ''
);

function onSelectChange(e: Event): void {
    zoomTo(Number((e.target as HTMLSelectElement).value));
}

function print(): void {
    window.print();
}
</script>

<style scoped>
.viewer-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 16px;
    background: #2c2c2c;
    color: #fff;
    position: sticky;
    top: 0;
    z-index: 100;
    gap: 12px;
}

.viewer-toolbar__controls {
    display: flex;
    align-items: center;
    gap: 8px;
}

select {
    background: #444;
    color: #fff;
    border: 1px solid #666;
    border-radius: 4px;
    padding: 4px 8px;
    font-size: 14px;
    cursor: pointer;
    min-width: 72px;
}

select:focus-visible {
    outline: 2px solid #6cb6ff;
    outline-offset: 2px;
    border-color: #888;
}

button {
    background: #444;
    color: #fff;
    border: 1px solid #666;
    border-radius: 4px;
    padding: 4px 12px;
    cursor: pointer;
    font-size: 14px;
}

button:hover:not(:disabled) {
    background: #555;
}

button:focus-visible {
    outline: 2px solid #6cb6ff;
    outline-offset: 2px;
}

button:disabled {
    opacity: 0.4;
    cursor: default;
}

@media print {
    .viewer-toolbar {
        display: none !important;
    }
}
</style>
