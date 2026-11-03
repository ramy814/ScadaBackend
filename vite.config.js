import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

// `npm run build:release` writes to public/build-platform, which is committed: the production
// server deploys by `git pull` only, and its untracked public/build still holds the legacy assets.
const buildDirectory = process.env.PLATFORM_BUILD_DIR || 'build';

export default defineConfig({
    plugins: [
        laravel({ input: ['resources/css/app.css', 'resources/js/app.js'], refresh: true, buildDirectory }),
        vue({ template: { transformAssetUrls: { base: null, includeAbsolute: false } } }),
    ],
    server: { watch: { ignored: ['**/storage/framework/views/**'] } },
});
