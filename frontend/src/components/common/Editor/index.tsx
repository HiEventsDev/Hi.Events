import {Link, RichTextEditor} from "@mantine/tiptap";
import {useEditor} from "@tiptap/react";
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import TextAlign from '@tiptap/extension-text-align';
import Image from '@tiptap/extension-image';
import React, {useCallback, useEffect, useState} from "react";
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
            Image,
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
    
    const addImage = useCallback(() => {
        const url = window.prompt('Image URL');

        if (url && editor) {
          editor.chain().focus().setImage({ src: url }).run();
        }
    }, [editor]);

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
                            <RichTextEditor.ControlsGroup>
                                <button
                                    data-rich-text-editor-control
                                    className="mantine-focus-auto mantine-RichTextEditor-control"
                                    onClick={addImage}
                                    style={{
                                        display: 'flex',
                                        border: '#ced4da 1px solid',
                                        background: 'transparent',
                                        cursor: 'pointer',
                                        padding: '2px 4px',
                                    }}
                                >
                                    <svg viewBox="0 0 20 20" width={"18"}><path d="M1.201 1C.538 1 0 1.47 0 2.1v14.363c0 .64.534 1.037 1.186 1.037h9.494a2.97 2.97 0 0 1-.414-.287 2.998 2.998 0 0 1-1.055-2.03 3.003 3.003 0 0 1 .693-2.185l.383-.455-.02.018-3.65-3.41a.695.695 0 0 0-.957-.034L1.5 13.6V2.5h15v5.535a2.97 2.97 0 0 1 1.412.932l.088.105V2.1c0-.63-.547-1.1-1.2-1.1H1.202Zm11.713 2.803a2.146 2.146 0 0 0-2.049 1.992 2.14 2.14 0 0 0 1.28 2.096 2.13 2.13 0 0 0 2.644-3.11 2.134 2.134 0 0 0-1.875-.978Z"></path><path d="M15.522 19.1a.79.79 0 0 0 .79-.79v-5.373l2.059 2.455a.79.79 0 1 0 1.211-1.015l-3.352-3.995a.79.79 0 0 0-.995-.179.784.784 0 0 0-.299.221l-3.35 3.99a.79.79 0 1 0 1.21 1.017l1.936-2.306v5.185c0 .436.353.79.79.79Z"></path><path d="M15.522 19.1a.79.79 0 0 0 .79-.79v-5.373l2.059 2.455a.79.79 0 1 0 1.211-1.015l-3.352-3.995a.79.79 0 0 0-.995-.179.784.784 0 0 0-.299.221l-3.35 3.99a.79.79 0 1 0 1.21 1.017l1.936-2.306v5.185c0 .436.353.79.79.79Z"></path></svg>
                                </button >
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
