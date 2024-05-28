import {Link, RichTextEditor} from "@mantine/tiptap";
import {useEditor} from "@tiptap/react";
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import TextAlign from '@tiptap/extension-text-align';
import {useEffect} from "react";
import {InputDescription, InputError, InputLabel} from "@mantine/core";
import classes from "./Editor.module.scss";
import classNames from "classnames";

interface EditorProps {
    onChange: (value: string) => void;
    value: string;
    label?: string;
    description?: string;
    required?: boolean;
    className?: string;
    error?: string;
}

export const Editor = ({
                           error,
                           onChange,
                           value,
                           label = '',
                           required = false,
                           className = '',
                           description = ''
                       }: EditorProps) => {
    const editor = useEditor({
        extensions: [
            StarterKit,
            Underline,
            Link,
            TextAlign.configure({types: ['heading', 'paragraph']}),
        ],
        onUpdate: (a) => {
            onChange(a.editor.getHTML());
        },
    });

    useEffect(() => {
        if (value) {
            if (value !== editor?.getHTML()) {
                editor?.commands.setContent(value, false, {preserveWhitespace: "full"});
            }
        }
    }, [value]);

    return (
        <div className={classNames([classes.inputWrapper, className])}>
            {label && <InputLabel required={required} onClick={() => editor?.commands.focus()}>{label}</InputLabel>}
            {description && <InputDescription>{description}</InputDescription>}
            <RichTextEditor editor={editor}>
                <RichTextEditor.Toolbar>
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
                </RichTextEditor.Toolbar>

                <RichTextEditor.Content/>
            </RichTextEditor>
            {error && (
                <div className={classes.error}>
                    <InputError>{error}</InputError>
                </div>
            )}
        </div>
    );
}