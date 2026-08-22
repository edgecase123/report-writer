import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [vue()],
    build: {
        outDir: '../../js/dist/reporting-viewer',
        emptyOutDir: true,
        rollupOptions: {
            output: {
                entryFileNames: 'reporting-viewer.js',
                chunkFileNames: 'reporting-viewer-[name].js',
                assetFileNames: 'reporting-viewer[extname]',
            },
        },
    },
    base: '/js/dist/reporting-viewer/',
});
