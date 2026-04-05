import {defineConfig} from "vite";
import {lingui} from "@lingui/vite-plugin";
import react from "@vitejs/plugin-react";
import {copy} from "vite-plugin-copy";
import {VitePWA} from "vite-plugin-pwa";
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

export default defineConfig({
    optimizeDeps: {
        include: ["react-router"]
    },
    server: {
        hmr: {
            port: 24678,
            protocol: "ws",
        },
    },
    plugins: [
        react({
            babel: {
                plugins: ["macros"],
            },
        }),
        lingui(),
        copy({
            targets: [{src: "src/embed/widget.js", dest: "public"}],
            hook: "writeBundle",
        }),
        VitePWA({
            registerType: "autoUpdate",
            includeAssets: [
                "manifest-icons/favicon.ico",
                "manifest-icons/favicon.svg",
                "manifest-icons/apple-touch-icon.png",
            ],
            manifest: false, // Use existing site.webmanifest
            workbox: {
                globPatterns: ["**/*.{js,css,html,ico,png,svg,woff2}"],
                runtimeCaching: [
                    {
                        urlPattern: /^https:\/\/.*\/api\/.*/i,
                        handler: "NetworkFirst",
                        options: {
                            cacheName: "api-cache",
                            expiration: {
                                maxEntries: 100,
                                maxAgeSeconds: 60 * 60, // 1 hour
                            },
                            networkTimeoutSeconds: 5,
                        },
                    },
                    {
                        urlPattern: /\.(?:png|jpg|jpeg|svg|gif|webp)$/,
                        handler: "CacheFirst",
                        options: {
                            cacheName: "image-cache",
                            expiration: {
                                maxEntries: 200,
                                maxAgeSeconds: 60 * 60 * 24 * 30, // 30 days
                            },
                        },
                    },
                    {
                        urlPattern: /\.(?:woff2?|ttf|eot)$/,
                        handler: "CacheFirst",
                        options: {
                            cacheName: "font-cache",
                            expiration: {
                                maxEntries: 30,
                                maxAgeSeconds: 60 * 60 * 24 * 365, // 1 year
                            },
                        },
                    },
                ],
                navigateFallback: "/index.html",
                navigateFallbackDenylist: [/^\/api\//, /^\/public\//],
            },
        }),
    ],
    define: {
        "process.env": process.env,
        "__APP_VERSION__": JSON.stringify(getVersion()),
    },
    ssr: {
        noExternal: ["react-helmet-async"],
    },
    css: {
        preprocessorOptions: {
            scss: {
                api: "modern-compiler",
            }
        }
    }
});
