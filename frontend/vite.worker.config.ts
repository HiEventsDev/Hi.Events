import {defineConfig} from "vite";
import {lingui} from "@lingui/vite-plugin";
import react from "@vitejs/plugin-react";
import {existsSync, readFileSync} from "fs";
import {resolve} from "path";

function getVersion(): string {
    const candidates = [
        resolve(__dirname, "../VERSION"),
        resolve(__dirname, "../../VERSION"),
        "/app/VERSION",
    ];
    for (const path of candidates) {
        if (existsSync(path)) {
            return readFileSync(path, "utf-8").trim();
        }
    }
    return "unknown";
}

/**
 * Bundles the Cloudflare Worker (including SSR + Lingui catalogs) for wrangler.
 */
export default defineConfig({
    plugins: [
        react({
            babel: {
                plugins: ["macros"],
            },
        }),
        lingui(),
    ],
    define: {
        "__APP_VERSION__": JSON.stringify(getVersion()),
    },
    resolve: {
        alias: {
            process: resolve(__dirname, "src/ssr/process-shim.js"),
        },
        conditions: ["worker", "browser", "import", "module", "default"],
    },
    build: {
        ssr: true,
        outDir: "dist/worker",
        emptyOutDir: true,
        copyPublicDir: false,
        rollupOptions: {
            input: "worker.ts",
            output: {
                entryFileNames: "worker.js",
                format: "es",
            },
        },
    },
    ssr: {
        target: "webworker",
        noExternal: true,
        resolve: {
            conditions: ["worker", "browser", "import", "module", "default"],
        },
    },
    css: {
        preprocessorOptions: {
            scss: {
                api: "modern-compiler",
            },
        },
    },
});
