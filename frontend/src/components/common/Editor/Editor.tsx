import {Link, RichTextEditor} from "@mantine/tiptap";
import {useEditor} from "@tiptap/react";
import {BubbleMenu} from "@tiptap/react/menus";
import {Placeholder} from "@tiptap/extensions";
import StarterKit from '@tiptap/starter-kit';
import {TextAlign} from '@tiptap/extension-text-align';
import {Color, TextStyle} from '@tiptap/extension-text-style';
import React, {useEffect, useState} from "react";
import {InputDescription, InputError, InputLabel, MantineFontSize} from "@mantine/core";
import classes from "./Editor.module.scss";
import classNames from "classnames";
import {Trans} from "@lingui/macro";
import {InsertImageControl} from "./Controls/InsertImageControl";
import {InlineInsertMenu} from "./Controls/InlineInsertMenu";
import {ImageResize} from "./Extensions/ImageResizeExtension";
import {Extension} from '@tiptap/core';

export interface EditorProps {
    onChange: (value: string) => void;
    value: string;
    label?: React.ReactNode;
    description?: React.ReactNode;
    required?: boolean;
    className?: string;
    error?: string | React.ReactNode;
    editorType?: 'full' | 'simple' | 'inline';
    placeholder?: string;
    autoFocus?: boolean;
    ariaLabel?: string;
    dataTestId?: string;
    maxLength?: number;
    size?: MantineFontSize;
    additionalExtensions?: Extension[];
    additionalToolbarControls?: React.ReactNode;
}

export const Editor = ({
                           error,
                           onChange,
                           value,
                           label = '',
                           required = false,
                           className = '',
                           description = '',
                           editorType = 'full',
                           maxLength,
                           size = 'md',
                           additionalExtensions = [],
                           additionalToolbarControls,
                           placeholder,
                           autoFocus = false,
                           ariaLabel,
                           dataTestId,
                       }: EditorProps) => {
    const [charError, setCharError] = useState<string | null | React.ReactNode>(null);

    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                link: false,
                paragraph: {
                    HTMLAttributes: {
                        style: 'margin: 0.5em 0;'
                    }
                },
                hardBreak: {
                    HTMLAttributes: {
                        'data-type': 'hard-break'
                    }
                }
            }),
            Link,
            TextAlign.configure({types: ['heading', 'paragraph']}),
            ImageResize,
            TextStyle,
            Color,
            ...(placeholder ? [Placeholder.configure({placeholder})] : []),
            ...additionalExtensions
        ],
        autofocus: autoFocus ? 'end' : false,
        editorProps: {
            attributes: {
                ...(ariaLabel ? {'aria-label': ariaLabel} : {}),
                ...(dataTestId ? {'data-testid': dataTestId} : {}),
            },
        },
        onUpdate: ({editor}) => {
            const html = editor.getHTML();
            const htmlLength = html.length;

            if (maxLength && htmlLength > maxLength) {
                setCharError(`Character limit exceeded: ${htmlLength}/${maxLength}`);
            } else {
                setCharError(null);
            }

            onChange(html);
        },
    });

    useEffect(() => {
        if (value && editor) {
            if (value !== editor.getHTML()) {
                editor.commands.setContent(value, {emitUpdate: false, parseOptions: {preserveWhitespace: "full"}});
            }
            const htmlLength = value.length;

            if (maxLength && htmlLength > maxLength) {
                setCharError(<Trans>HTML character limit exceeded: {htmlLength}/{maxLength}</Trans>);
            } else {
                setCharError(null);
            }
        }
    }, [value, editor, maxLength]);

    return (
        <div className={classNames([classes.inputWrapper, className])}>
            {label && <InputLabel size={size} required={required}
                                  onClick={() => editor?.commands.focus()}>{label}</InputLabel>}
            {description && (
                <div style={{marginBottom: 5}}>
                    <InputDescription size={size}>{description}</InputDescription>
                </div>
            )}
            <RichTextEditor
                variant={'subtle'}
                editor={editor}
                className={editorType === 'inline' ? classes.inline : undefined}
            >
                {editorType === 'inline' && editor && (
                    <>
                        <BubbleMenu editor={editor} className={classes.floatingControls}>
                            <RichTextEditor.ControlsGroup>
                                <RichTextEditor.Bold/>
                                <RichTextEditor.Italic/>
                                <RichTextEditor.Underline/>
                                <RichTextEditor.Link/>
                            </RichTextEditor.ControlsGroup>
                            <RichTextEditor.ControlsGroup>
                                <RichTextEditor.H2/>
                                <RichTextEditor.H3/>
                                <RichTextEditor.BulletList/>
                                <RichTextEditor.OrderedList/>
                            </RichTextEditor.ControlsGroup>
                        </BubbleMenu>
                        <InlineInsertMenu editor={editor}/>
                    </>
                )}

                {editorType !== 'inline' && <RichTextEditor.Toolbar sticky className={classes.toolbar}>
                    {editorType === 'full' && (
                        <>
                            <RichTextEditor.ControlsGroup>
                                <RichTextEditor.Bold/>
                                <RichTextEditor.Italic/>
                                <RichTextEditor.Underline/>
                                <RichTextEditor.ClearFormatting/>
                                <RichTextEditor.ColorPicker
                                    colors={[
                                        '#25262b',
                                        '#868e96',
                                        '#fa5252',
                                        '#e64980',
                                        '#be4bdb',
                                        '#7950f2',
                                        '#4c6ef5',
                                        '#228be6',
                                        '#15aabf',
                                        '#12b886',
                                        '#40c057',
                                        '#82c91e',
                                        '#fab005',
                                        '#fd7e14',
                                    ]}
                                />
                            </RichTextEditor.ControlsGroup>

                            <RichTextEditor.ControlsGroup>
                                <RichTextEditor.H1/>
                                <RichTextEditor.H2/>
                                <RichTextEditor.H3/>
                                <RichTextEditor.H4/>
                            </RichTextEditor.ControlsGroup>

                            <RichTextEditor.ControlsGroup>
                                <RichTextEditor.BulletList/>
                                <RichTextEditor.OrderedList/>
                            </RichTextEditor.ControlsGroup>

                            <RichTextEditor.ControlsGroup>
                                <RichTextEditor.Link/>
                                <RichTextEditor.Unlink/>
                            </RichTextEditor.ControlsGroup>

                            <RichTextEditor.ControlsGroup>
                                <RichTextEditor.AlignLeft/>
                                <RichTextEditor.AlignCenter/>
                                <RichTextEditor.AlignJustify/>
                                <RichTextEditor.AlignRight/>
                            </RichTextEditor.ControlsGroup>
                            <RichTextEditor.ControlsGroup>
                                <InsertImageControl/>
                            </RichTextEditor.ControlsGroup>
                        </>
                    )}

                    {editorType === 'simple' && (
                        <>
                            <RichTextEditor.ControlsGroup>
                                <RichTextEditor.Bold/>
                                <RichTextEditor.Italic/>
                                <RichTextEditor.Underline/>
                                <RichTextEditor.ClearFormatting/>
                                <RichTextEditor.ColorPicker
                                    colors={[
                                        '#25262b',
                                        '#868e96',
                                        '#fa5252',
                                        '#e64980',
                                        '#be4bdb',
                                        '#7950f2',
                                        '#4c6ef5',
                                        '#228be6',
                                        '#15aabf',
                                        '#12b886',
                                        '#40c057',
                                        '#82c91e',
                                        '#fab005',
                                        '#fd7e14',
                                    ]}
                                />
                            </RichTextEditor.ControlsGroup>

                            <RichTextEditor.ControlsGroup>
                                <RichTextEditor.Link/>
                                <RichTextEditor.Unlink/>
                            </RichTextEditor.ControlsGroup>

                            <RichTextEditor.ControlsGroup>
                                <RichTextEditor.AlignLeft/>
                                <RichTextEditor.AlignCenter/>
                                <RichTextEditor.AlignRight/>
                            </RichTextEditor.ControlsGroup>

                            <RichTextEditor.ControlsGroup>
                                <RichTextEditor.BulletList/>
                                <RichTextEditor.OrderedList/>
                            </RichTextEditor.ControlsGroup>
                            <RichTextEditor.ControlsGroup>
                                <InsertImageControl/>
                            </RichTextEditor.ControlsGroup>
                        </>
                    )}
                    
                    {additionalToolbarControls}
                </RichTextEditor.Toolbar>}

                <RichTextEditor.Content/>
            </RichTextEditor>
            {(charError || error) && (
                <div className={classes.error}>
                    <InputError>{error || charError}</InputError>
                </div>
            )}
        </div>
    );
};
