import { fileURLToPath, URL } from 'node:url';
import { existsSync, realpathSync } from 'node:fs';
import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vite';

// The shared pages live in social-images-core. In this repo it's a
// Composer dependency; beside it, during development, it's the folder next door.
const local = fileURLToPath(new URL('../social-images-core/resources', import.meta.url));
const vendor = fileURLToPath(new URL('./vendor/1994/social-images-core/resources', import.meta.url));
const core = existsSync(vendor) ? realpathSync(vendor) : local;

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@social-images/designer': `${core}/designer`,
            '@social-images/ui': fileURLToPath(new URL('./resources/js/ui/index.js', import.meta.url)),
            vue: fileURLToPath(new URL('./node_modules/vue/dist/vue.runtime.esm-bundler.js', import.meta.url)),
        },
    },
    define: {
        'process.env.NODE_ENV': JSON.stringify('production'),
        __VUE_OPTIONS_API__: true,
        __VUE_PROD_DEVTOOLS__: false,
        __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: false,
    },
    build: {
        outDir: 'src/web/assets/cp/dist',
        emptyOutDir: true,
        cssCodeSplit: false,
        lib: {
            entry: fileURLToPath(new URL('./resources/js/cp.js', import.meta.url)),
            formats: ['iife'],
            name: 'SocialImagesCp',
            fileName: () => 'cp.js',
            cssFileName: 'cp',
        },
        rollupOptions: {
            output: { assetFileNames: 'cp.[ext]' },
        },
    },
    server: { fs: { allow: ['..'] } },
});
