import {t} from "@lingui/macro";
import {RichTextEditor, useRichTextEditorContext} from "@mantine/tiptap";
import {IconSeparatorHorizontal} from "@tabler/icons-react";

export const InsertDividerControl = () => {
    const {editor} = useRichTextEditorContext();

    return (
        <RichTextEditor.Control
            onClick={() => editor?.chain().focus().setHorizontalRule().run()}
            aria-label={t`Insert divider`}
            title={t`Insert divider`}
        >
            <IconSeparatorHorizontal stroke={1.5} size="1rem"/>
        </RichTextEditor.Control>
    );
};
