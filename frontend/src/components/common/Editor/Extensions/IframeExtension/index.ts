import {mergeAttributes, Node} from '@tiptap/core';

export interface IframeOptions {
    allowFullscreen: boolean;
    HTMLAttributes: {
        [key: string]: any;
    };
}

declare module '@tiptap/core' {
    interface Commands<ReturnType> {
        iframe: {
            setIframe: (options: { src: string; width?: string; height?: string; title?: string }) => ReturnType;
        };
    }
}

export const Iframe = Node.create<IframeOptions>({
    name: 'iframe',

    group: 'block',

    atom: true,

    addOptions() {
        return {
            allowFullscreen: true,
            HTMLAttributes: {
                class: 'iframe-wrapper',
            },
        };
    },

    addAttributes() {
        return {
            src: {
                default: null,
            },
            width: {
                default: '100%',
            },
            height: {
                default: '315px',
            },
            frameborder: {
                default: '0',
            },
            title: {
                default: null,
            },
            allow: {
                default: 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share',
            },
            style: {
                default: 'width: 100%; height: 315px; border: 1px solid #ccc;',
            },
        };
    },

    parseHTML() {
        return [
            {
                tag: 'iframe',
            },
        ];
    },

    renderHTML({HTMLAttributes}) {
        return ['div', this.options.HTMLAttributes, ['iframe', mergeAttributes(HTMLAttributes)]];
    },

    addCommands() {
        return {
            setIframe:
                (options) =>
                    ({commands}) => {
                        return commands.insertContent({
                            type: this.name,
                            attrs: options,
                        });
                    },
        };
    },
});
