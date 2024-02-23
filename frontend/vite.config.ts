import { defineConfig } from "vite";
import { lingui } from "@lingui/vite-plugin";
import react from "@vitejs/plugin-react";

export default defineConfig({
    plugins: [
        react({
            babel:  {
                plugins: ["macros"],
            },
        }),
        lingui(),
    ],
    define: {
        'process.env': process.env
    }
});


// export default defineConfig(({command}) => {
    // if (command === 'build') {
    //     return {
    //         build: {
    //             rollupOptions: {
    //                 input: {
    //                     main: "index.html",
    //                     embed: "src/embed/embed.ts",
    //                 },
    //                 output: {
    //                     entryFileNames: (chunkInfo) => {
    //                         // don't include hash in embed.js as this is a third-party embed script
    //                         if (chunkInfo.name === 'embed') {
    //                             return `[name].js`;
    //                         }
    //                         return `assets/[name].[hash].js`;
    //                     },
    //                     chunkFileNames: `assets/[name].[hash].js`,
    //                     manualChunks: {
    //                         embed: [path.resolve(__dirname, 'src/embed/embed.ts')]
    //                     }
    //                 },
    //             },
    //         },
    //     }
    // }

//     if (command === 'serve') {
//         return {
//             build: {
//                 rollupOptions: {
//                     input: {
//                         main: "index.html"
//                     },
//                 },
//             },
//         };
//     }
// })
