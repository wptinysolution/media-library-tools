import path from "path"
import tailwindcss from "@tailwindcss/vite";
import react from "@vitejs/plugin-react"
import { defineConfig } from "vite"
import { viteStaticCopy } from "vite-plugin-static-copy";
import type { Plugin } from "vite";
import type { OutputBundle } from "rollup";

/**
 * Wraps one or more specific JS files in an IIFE.
 * @param targetFiles - A string or array of strings representing file names in the bundle
 */
export function wrapSpecificFilesInIIFE(targetFiles: string | string[]): Plugin {
    const files = Array.isArray(targetFiles) ? targetFiles : [targetFiles];
    return {
        name: "wrap-specific-files-in-iife",
        generateBundle(_options: unknown, bundle: OutputBundle) {
            for (const file of files) {
                const chunk = bundle[file];
                if (chunk && chunk.type === "chunk") {
                    chunk.code = `(() => {${chunk.code}})();`;
                }
            }
        },
    };
}

export default defineConfig({
    plugins: [
        react(),
        tailwindcss(),
        viteStaticCopy({
            targets: [
                {
                    src: "src/images/",   // source folder
                    dest: "./",            // destination inside "assets/"
                }
            ],
        }),
        wrapSpecificFilesInIIFE([
            "js/backend/admin-settings.js"
        ]),
    ],
    build: {
        outDir: 'assets', // compiled files output
        rollupOptions: {
            input: {
                'js/backend/admin-settings': path.resolve(__dirname, 'src/js/admin-settings.jsx'),
                'css/backend/admin-settings': path.resolve(__dirname, 'src/scss/admin-settings.scss'),
            },
            output: {
                entryFileNames: "[name].js",
                assetFileNames: (assetInfo) => {
                    // Rollup 4+ uses `names` instead of deprecated `name`
                    const names = assetInfo.names || [];
                    const firstName = names[0] || ""; // handle array safely
                    if (firstName.endsWith(".css")) {
                        if (firstName.includes("settings")) {
                            // Force this CSS to admin/css folder
                           return "css/backend/admin-settings.css";
                        }
                        // Preserve original output structure
                        return "[name].css";
                    }
                    return "[name][extname]";
                },
            },
        },
    },
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'src'),
        },
    },
});