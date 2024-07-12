import {Link, RichTextEditor} from "@mantine/tiptap";
import {useEditor} from "@tiptap/react";
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import TextAlign from '@tiptap/extension-text-align';
import React, {useEffect, useState} from "react";
import {InputDescription, InputError, InputLabel} from "@mantine/core";
import classes from "./Editor.module.scss";
import classNames from "classnames";
import {Trans} from "@lingui/macro";

interface EditorProps {
    onChange: (value: string) => void;
    value: string;
    label?: string;
    description?: string;
    required?: boolean;
    className?: string;
    error?: string;
    editorType?: 'full' | 'simple';
    maxLength?: number;
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
                       }: EditorProps) => {
    const [charError, setCharError] = useState<string | null | React.ReactNode>(null);

    const editor = useEditor({
        extensions: [
            StarterKit,
            Underline,
            Link,
            TextAlign.configure({types: ['heading', 'paragraph']}),
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
            {label && <InputLabel required={required} onClick={() => editor?.commands.focus()}>{label}</InputLabel>}
            {description && (
                <div style={{marginBottom: 5}}>
                    <InputDescription>{description}</InputDescription>
                </div>
            )}
            <RichTextEditor editor={editor}>
                <RichTextEditor.Toolbar>
                    {editorType === 'full' && (
                        <>
                            <RichTextEditor.ControlsGroup>
                                <RichTextEditor.Bold/>
                                <RichTextEditor.Italic/>
                                <RichTextEditor.Underline/>
                                <RichTextEditor.ClearFormatting/>
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
                        </>
                    )}

                    {editorType === 'simple' && (
                        <>
                            <RichTextEditor.ControlsGroup>
                                <RichTextEditor.Bold/>
                                <RichTextEditor.Italic/>
                                <RichTextEditor.Underline/>
                                <RichTextEditor.ClearFormatting/>
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
                        </>
                    )}
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
