import {mergeAttributes, Node} from '@tiptap/core';
import {ReactNodeViewRenderer} from '@tiptap/react';
import {TokenComponent} from './TokenComponent';

declare module '@tiptap/core' {
    interface Commands<ReturnType> {
        liquidToken: {
            insertLiquidToken: (tokenName: string, tokenDescription?: string) => ReturnType;
        };
    }
}

export const LiquidToken = Node.create({
    name: 'liquidToken',
    group: 'inline',
    inline: true,
    atom: true,

    addAttributes() {
        return {
            tokenName: {
                default: '',
                parseHTML: element => element.getAttribute('data-token-name'),
                renderHTML: attributes => ({
                    'data-token-name': attributes.tokenName,
                }),
            },
            tokenDescription: {
                default: '',
                parseHTML: element => element.getAttribute('data-token-description'),
                renderHTML: attributes => ({
                    'data-token-description': attributes.tokenDescription,
                }),
            },
        };
    },

    parseHTML() {
        return [
            {
                tag: 'span[data-type="liquid-token"]',
            },
        ];
    },

    renderHTML({HTMLAttributes}) {
        return ['span', mergeAttributes({'data-type': 'liquid-token'}, HTMLAttributes)];
    },

    addNodeView() {
        return ReactNodeViewRenderer(TokenComponent);
    },

    addCommands() {
        return {
            insertLiquidToken: (tokenName: string, tokenDescription?: string) => ({commands}) => {
                return commands.insertContent({
                    type: this.name,
                    attrs: {
                        tokenName,
                        tokenDescription: tokenDescription || '',
                    },
                });
            },
        };
    },
});

export default LiquidToken;
