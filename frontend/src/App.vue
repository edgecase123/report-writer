<template>
    <div class="viewer-shell">
        <ViewerToolbar />
        <ReportCanvas />
    </div>
</template>

<script setup lang="ts">
import ViewerToolbar from './components/ViewerToolbar.vue';
import ReportCanvas from './components/ReportCanvas.vue';
</script>

<style>
*, *::before, *::after { box-sizing: border-box; }

html, body {
    margin: 0;
    padding: 0;
    height: 100%;
}

.viewer-shell {
    display: flex;
    flex-direction: column;
    height: 100vh;
}

@media print {
    .viewer-toolbar { display: none !important; }
    .viewer-canvas  { overflow: visible; height: auto; background: none; padding: 0; }
    .report-inner   { transform: none !important; }
}
</style>
