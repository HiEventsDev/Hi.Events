import {Link, RichTextEditor} from "@mantine/tiptap";
import {useEditor} from "@tiptap/react";
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import TextAlign from '@tiptap/extension-text-align';
import Image from '@tiptap/extension-image';
import TextStyle from '@tiptap/extension-text-style';
import Color from '@tiptap/extension-color';
import React, {useEffect, useState} from "react";
import {InputDescription, InputError, InputLabel, MantineFontSize} from "@mantine/core";
import classes from "./Editor.module.scss";
import classNames from "classnames";
import {Trans} from "@lingui/macro";
import {InsertImageControl} from "./Controls/InsertImageControl";
import {ImageResize} from "./Extensions/ImageResizeExtension";
import {Extension} from '@tiptap/core';

interface EditorProps {
    onChange: (value: string) => void;
    value: string;
    label?: React.ReactNode;
    description?: React.ReactNode;
    required?: boolean;
    className?: string;
    error?: string | React.ReactNode;
    editorType?: 'full' | 'simple';
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
                       }: EditorProps) => {
    const [charError, setCharError] = useState<string | null | React.ReactNode>(null);

    const editor = useEditor({
        extensions: [
            StarterKit.configure({
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
            Underline,
            Link,
            TextAlign.configure({types: ['heading', 'paragraph']}),
            Image,
            ImageResize,
            TextStyle,
            Color,
            ...additionalExtensions
        ],
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
                editor.commands.setContent(value, false, {preserveWhitespace: "full"});
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
            <RichTextEditor variant={'subtle'} editor={editor}>
                <RichTextEditor.Toolbar sticky className={classes.toolbar}>
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
                </RichTextEditor.Toolbar>

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
