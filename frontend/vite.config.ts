import { defineConfig } from "vite";
import { lingui } from "@lingui/vite-plugin";
import react from "@vitejs/plugin-react";
import {copy} from 'vite-plugin-copy';
import rollupReplace from "@rollup/plugin-replace";

export default defineConfig({
    plugins: [
        rollupReplace({
            preventAssignment: true,
            values: {
              __DEV__: JSON.stringify(true),
              "process.env.NODE_ENV": JSON.stringify("development"),
            },
          }),
        react({
            babel:  {
                plugins: ["macros"],
            },
        }),
        lingui(),
        copy({
            targets: [
                { src: 'src/embed/widget.js', dest: 'public' }
            ],
            hook: 'writeBundle'
        })
    ],
    define: {
        'process.env': process.env
    }
});
