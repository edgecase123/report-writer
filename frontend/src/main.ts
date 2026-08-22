import { createApp } from 'vue';
import App from './App.vue';
import { reportUrl } from './state/viewerState';

const mountEl = document.getElementById('reporting-viewer-app');

if (mountEl) {
    reportUrl.value = mountEl.dataset.reportUrl || '';
    createApp(App).mount(mountEl);
} else {
    console.error(
        'reporting-viewer: mount element #reporting-viewer-app not found. ' +
        'Add <div id="reporting-viewer-app" data-report-url="…"></div> to the host page.'
    );
}
