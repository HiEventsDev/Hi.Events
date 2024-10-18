import {NodeViewProps} from '@tiptap/core';
import Image from '@tiptap/extension-image';

/**
 * Adapted from https://github.com/bae-sh/tiptap-extension-resize-image/blob/main/lib/imageResize.ts
 */
export const ImageResize = Image.extend({
    addAttributes() {
        return {
            src: {
                default: null,
            },
            alt: {
                default: null,
            },
            style: {
                default: 'width: 100%; height: auto; cursor: pointer;',
                parseHTML: (element: HTMLElement) => {
                    const width = element.getAttribute('width');
                    return width
                        ? `width: ${width}px; height: auto; cursor: pointer;`
                        : `${element.style.cssText}`;
                },
            },
            title: {
                default: null,
            },
            loading: {
                default: null,
            },
            srcset: {
                default: null,
            },
            sizes: {
                default: null,
            },
            crossorigin: {
                default: null,
            },
            usemap: {
                default: null,
            },
            ismap: {
                default: null,
            },
            width: {
                default: null,
            },
            height: {
                default: null,
            },
            referrerpolicy: {
                default: null,
            },
            longdesc: {
                default: null,
            },
            decoding: {
                default: null,
            },
            class: {
                default: null,
            },
            id: {
                default: null,
            },
            name: {
                default: null,
            },
            draggable: {
                default: true,
            },
            tabindex: {
                default: null,
            },
            'aria-label': {
                default: null,
            },
            'aria-labelledby': {
                default: null,
            },
            'aria-describedby': {
                default: null,
            },
        };
    },
    addNodeView() {
        return ({node, editor, getPos}: NodeViewProps) => {
            const {
                view,
                options: {editable},
            } = editor;
            const {style} = node.attrs;
            const $wrapper: HTMLDivElement = document.createElement('div');
            const $container: HTMLDivElement = document.createElement('div');
            const $img: HTMLImageElement = document.createElement('img');
            const iconStyle = 'width: 24px; height: 24px; cursor: pointer; margin-bottom: 0;';

            const dispatchNodeView = () => {
                if (typeof getPos === 'function') {
                    const newAttrs = {
                        ...node.attrs,
                        style: `${$img.style.cssText}`,
                    };
                    view.dispatch(view.state.tr.setNodeMarkup(getPos(), null, newAttrs));
                }
            };

            const paintPositionController = () => {
                const $positionController: HTMLDivElement = document.createElement('div');

                const $leftController: HTMLImageElement = document.createElement('img');
                const $centerController: HTMLImageElement = document.createElement('img');
                const $rightController: HTMLImageElement = document.createElement('img');

                const controllerMouseOver = (e: MouseEvent) => {
                    (e.target as HTMLElement).style.opacity = '0.3';
                };

                const controllerMouseOut = (e: MouseEvent) => {
                    (e.target as HTMLElement).style.opacity = '1';
                };

                $positionController.setAttribute(
                    'style',
                    'position: absolute; top: 0%; left: 50%; width: 100px; height: 25px; z-index: 999; background-color: rgba(255, 255, 255, 0.7); border-radius: 4px; border: 2px solid #6C6C6C; cursor: pointer; transform: translate(-50%, -50%); display: flex; justify-content: space-between; align-items: center; padding: 0 10px;',
                );

                $leftController.setAttribute(
                    'src',
                    'https://fonts.gstatic.com/s/i/short-term/release/materialsymbolsoutlined/format_align_left/default/20px.svg',
                );
                $leftController.setAttribute('style', iconStyle);
                $leftController.addEventListener('mouseover', controllerMouseOver);
                $leftController.addEventListener('mouseout', controllerMouseOut);

                $centerController.setAttribute(
                    'src',
                    'https://fonts.gstatic.com/s/i/short-term/release/materialsymbolsoutlined/format_align_center/default/20px.svg',
                );
                $centerController.setAttribute('style', iconStyle);
                $centerController.addEventListener('mouseover', controllerMouseOver);
                $centerController.addEventListener('mouseout', controllerMouseOut);

                $rightController.setAttribute(
                    'src',
                    'https://fonts.gstatic.com/s/i/short-term/release/materialsymbolsoutlined/format_align_right/default/20px.svg',
                );
                $rightController.setAttribute('style', iconStyle);
                $rightController.addEventListener('mouseover', controllerMouseOver);
                $rightController.addEventListener('mouseout', controllerMouseOut);

                $leftController.addEventListener('click', () => {
                    $img.setAttribute('style', `${$img.style.cssText} margin: 0 auto 0 0;`);
                    dispatchNodeView();
                });
                $centerController.addEventListener('click', () => {
                    $img.setAttribute('style', `${$img.style.cssText} margin: 0 auto;`);
                    dispatchNodeView();
                });
                $rightController.addEventListener('click', () => {
                    $img.setAttribute('style', `${$img.style.cssText} margin: 0 0 0 auto;`);
                    dispatchNodeView();
                });

                $positionController.appendChild($leftController);
                $positionController.appendChild($centerController);
                $positionController.appendChild($rightController);

                $container.appendChild($positionController);
            };

            $wrapper.setAttribute('style', `display: flex;`);
            $wrapper.appendChild($container);

            $container.setAttribute('style', `${style}`);
            $container.appendChild($img);

            Object.entries(node.attrs).forEach(([key, value]) => {
                if (value === undefined || value === null) return;
                $img.setAttribute(key, value as string);
            });

            if (!editable) return {dom: $img};

            const dotsPosition = [
                'top: -4px; left: -4px; cursor: nwse-resize;',
                'top: -4px; right: -4px; cursor: nesw-resize;',
                'bottom: -4px; left: -4px; cursor: nesw-resize;',
                'bottom: -4px; right: -4px; cursor: nwse-resize;',
            ];

            let isResizing = false;
            let startX: number, startWidth: number;

            $container.addEventListener('click', () => {
                // Remove remaining dots and position controller
                if ($container.childElementCount > 3) {
                    for (let i = 0; i < 5; i++) {
                        $container.removeChild($container.lastChild as Node);
                    }
                }

                // Disable positioning for the time being as it's not working properly
                //paintPositionController();

                $container.setAttribute(
                    'style',
                    `position: relative; border: 1px dashed #6C6C6C; ${style} cursor: pointer;`,
                );

                Array.from({length: 4}, (_, index) => {
                    const $dot: HTMLDivElement = document.createElement('div');
                    $dot.setAttribute(
                        'style',
                        `position: absolute; width: 9px; height: 9px; border: 1.5px solid #6C6C6C; border-radius: 50%; ${dotsPosition[index]}`,
                    );

                    $dot.addEventListener('mousedown', (e: MouseEvent) => {
                        e.preventDefault();
                        isResizing = true;
                        startX = e.clientX;
                        startWidth = $container.offsetWidth;

                        const onMouseMove = (e: MouseEvent) => {
                            if (!isResizing) return;
                            const deltaX = index % 2 === 0 ? -(e.clientX - startX) : e.clientX - startX;
                            const newWidth = startWidth + deltaX;

                            $container.style.width = newWidth + 'px';
                            $img.style.width = newWidth + 'px';
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
                    $container.appendChild($dot);
                });
            });

            document.addEventListener('click', (e: MouseEvent) => {
                const $target = e.target as HTMLElement;
                const isClickInside = $container.contains($target) || $target.style.cssText === iconStyle;

                if (!isClickInside) {
                    const containerStyle = $container.getAttribute('style');
                    const newStyle = containerStyle?.replace('border: 1px dashed #6C6C6C;', '');
                    $container.setAttribute('style', newStyle as string);

                    if ($container.childElementCount > 3) {
                        for (let i = 0; i < 5; i++) {
                            $container.removeChild($container.lastChild as Node);
                        }
                    }
                }
            });

            return {
                dom: $wrapper,
            };
        };
    },
});
