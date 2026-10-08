import {useEffect, useState} from "react";
import {RichTextEditor} from "@mantine/tiptap";
import {Editor} from "@tiptap/react";
import {FloatingMenu} from "@tiptap/react/menus";
import {IconPlus} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import classNames from "classnames";
import {InsertImageControl} from "../InsertImageControl";
import classes from "../../Editor.module.scss";

export const InlineInsertMenu = ({editor}: { editor: Editor }) => {
    const [expanded, setExpanded] = useState(false);

    useEffect(() => {
        const collapse = () => setExpanded(false);
        editor.on('selectionUpdate', collapse);
        return () => {
            editor.off('selectionUpdate', collapse);
        };
    }, [editor]);

    return (
        <FloatingMenu
            editor={editor}
            className={classNames(classes.floatingControls, !expanded && classes.insertTrigger)}
            options={{placement: 'right', offset: 4}}
            shouldShow={({editor: menuEditor, state}) => {
                const {$anchor, empty} = state.selection;
                return menuEditor.isEditable
                    && menuEditor.isFocused
                    && empty
                    && !menuEditor.isEmpty
                    && $anchor.parent.type.name === 'paragraph'
                    && $anchor.parent.content.size === 0;
            }}
        >
            {expanded ? (
                <RichTextEditor.ControlsGroup>
                    <RichTextEditor.H2/>
                    <RichTextEditor.H3/>
                    <RichTextEditor.BulletList/>
                    <RichTextEditor.OrderedList/>
                    <InsertImageControl/>
                </RichTextEditor.ControlsGroup>
            ) : (
                <RichTextEditor.Control
                    onClick={() => setExpanded(true)}
                    aria-label={t`Insert`}
                    title={t`Insert`}
                >
                    <IconPlus stroke={1.5} size="1rem"/>
                </RichTextEditor.Control>
            )}
        </FloatingMenu>
    );
};
