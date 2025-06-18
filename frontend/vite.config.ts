import { lingui } from "@lingui/vite-plugin";
import react from "@vitejs/plugin-react";
import { defineConfig, loadEnv } from "vite";
import { copy } from "vite-plugin-copy";

export default defineConfig( ( { mode } ) => {
	const env = loadEnv(mode, process.cwd(), "");
	return {
		base: new URL(env.VITE_FRONTEND_URL).pathname || "/",
		optimizeDeps: {
			include: ["react-router"],
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
				targets: [{ src: "src/embed/widget.js", dest: "public" }],
				hook: "writeBundle",
			}),
		],
		define: {
			"process.env": process.env,
		},
		ssr: {
			noExternal: ["react-helmet-async"],
		},
		css: {
			preprocessorOptions: {
				scss: {
					api: "modern-compiler",
				},
			},
		},
	};
})
