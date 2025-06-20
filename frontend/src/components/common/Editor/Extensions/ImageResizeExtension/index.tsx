import Image from '@tiptap/extension-image';
import { NodeViewProps } from '@tiptap/react';

/**
 * Adapted from https://github.com/bae-sh/tiptap-extension-resize-image/blob/main/lib/imageResize.ts
 */
export const ImageResize = Image.extend({
    name: 'imageResize',
    addAttributes() {
        return {
            ...this.parent?.(),
            style: {
                default: 'width: 100%; height: auto; cursor: pointer;',
                parseHTML: (element: HTMLElement) => {
                    const width = element.getAttribute('width');
                    return width
                        ? `width: ${width}px; height: auto; cursor: pointer;`
                        : `${element.style.cssText}`;
                },
            },
        };
    },

    addNodeView() {
        return ({ node, editor, getPos }: NodeViewProps) => {
            const {
                view,
                options: { editable },
            } = editor;

            const { style } = node.attrs;

            const $wrapper = document.createElement('div');
            $wrapper.setAttribute('style', 'display: flex; align-items: center;');

            const $resizeWrapper = document.createElement('div');
            $resizeWrapper.setAttribute('style', `${style}; position: relative;`);

            const $img = document.createElement('img');
            Object.entries(node.attrs).forEach(([key, value]) => {
                if (value !== undefined && value !== null) {
                    $img.setAttribute(key, value);
                }
            });

            $img.style.marginBottom = '0px';
            $img.style.display = 'block';

            $resizeWrapper.appendChild($img);
            $wrapper.appendChild($resizeWrapper);

            if (!editable) return { dom: $wrapper };

            const isMobile = document.documentElement.clientWidth < 768;
            const dotPosition = isMobile ? '-8px' : '-4px';
            const dotsPosition = [
                `top: ${dotPosition}; left: ${dotPosition}; cursor: nwse-resize;`,
                `top: ${dotPosition}; right: ${dotPosition}; cursor: nesw-resize;`,
                `bottom: ${dotPosition}; left: ${dotPosition}; cursor: nesw-resize;`,
                `bottom: ${dotPosition}; right: ${dotPosition}; cursor: nwse-resize;`,
            ];

            let isResizing = false;
            let startX: number, startWidth: number;

            const $sizeIndicator = document.createElement('div');
            $sizeIndicator.setAttribute('style', `
        position: absolute;
        bottom: -24px;
        left: 0;
        background: rgba(0,0,0,0.7);
        color: white;
        font-size: 11px;
        padding: 2px 4px;
        border-radius: 4px;
        pointer-events: none;
        display: none;
      `);
            $resizeWrapper.appendChild($sizeIndicator);

            const updateSizeIndicator = () => {
                $sizeIndicator.innerText = `${$img.width}px × ${$img.height}px`;
                $sizeIndicator.style.display = 'block';
            };

            const hideSizeIndicator = () => {
                $sizeIndicator.style.display = 'none';
            };

            const dispatchNodeView = () => {
                if (typeof getPos === 'function') {
                    const newAttrs = {
                        ...node.attrs,
                        style: `${$img.style.cssText}`,
                    };
                    view.dispatch(view.state.tr.setNodeMarkup(getPos(), null, newAttrs));
                }
            };

            const addResizeDots = () => {
                removeResizeDots();
                $resizeWrapper.style.border = '1px dashed #6C6C6C';
                updateSizeIndicator();

                dotsPosition.forEach((pos, index) => {
                    const $dot = document.createElement('div');
                    $dot.setAttribute(
                        'style',
                        `position: absolute; width: ${isMobile ? 16 : 9}px; height: ${isMobile ? 16 : 9}px; border: 1.5px solid #6C6C6C; border-radius: 50%; background: white; ${pos}`
                    );

                    $dot.addEventListener('mousedown', (e: MouseEvent) => {
                        e.preventDefault();
                        isResizing = true;
                        startX = e.clientX;
                        startWidth = $resizeWrapper.offsetWidth;

                        const onMouseMove = (e: MouseEvent) => {
                            if (!isResizing) return;
                            const deltaX = index % 2 === 0 ? -(e.clientX - startX) : e.clientX - startX;
                            const newWidth = startWidth + deltaX;
                            $resizeWrapper.style.width = newWidth + 'px';
                            $img.style.width = newWidth + 'px';
                            updateSizeIndicator();
                        };

                        const onMouseUp = () => {
                            if (isResizing) {
                                isResizing = false;
                            }
                            dispatchNodeView();
                            document.removeEventListener('mousemove', onMouseMove);
                            document.removeEventListener('mouseup', onMouseUp);
                        };

                        document.addEventListener('mousemove', onMouseMove);
                        document.addEventListener('mouseup', onMouseUp);
                    });

                    $resizeWrapper.appendChild($dot);
                });
            };

            const removeResizeDots = () => {
                $resizeWrapper.style.border = '';
                Array.from($resizeWrapper.querySelectorAll('div')).forEach(dot => {
                    if (dot !== $sizeIndicator) dot.remove();
                });
                hideSizeIndicator();
            };

            const outsideClickHandler = (e: MouseEvent) => {
                if (!$resizeWrapper.contains(e.target as Node)) {
                    removeResizeDots();
                }
            };

            $resizeWrapper.addEventListener('click', (e) => {
                e.stopPropagation();
                addResizeDots();
            });

            document.addEventListener('click', outsideClickHandler);

            return {
                dom: $wrapper,
                destroy() {
                    document.removeEventListener('click', outsideClickHandler);
                },
            };
        };
    },
});
