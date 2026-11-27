import {useState} from "react";
import {t} from "@lingui/macro";
import {Button, Group, Modal, Portal, Stack, TextInput} from "@mantine/core";
import {RichTextEditor, useRichTextEditorContext} from "@mantine/tiptap";
import {IconSquareRoundedPlus} from "@tabler/icons-react";

const EMAIL_BUTTON_CLASS = 'he-button';

const isWebAddress = (value: string) => /^https?:\/\/\S+$/i.test(value.trim());

export const InsertButtonControl = () => {
    const {editor} = useRichTextEditorContext();
    const [isOpen, setOpen] = useState(false);
    const [label, setLabel] = useState('');
    const [url, setUrl] = useState('');
    const [urlError, setUrlError] = useState<string | null>(null);

    const close = () => {
        setOpen(false);
        setLabel('');
        setUrl('');
        setUrlError(null);
    };

    const insert = () => {
        if (!isWebAddress(url)) {
            setUrlError(t`Enter a link that starts with https://`);
            return;
        }
        editor?.chain().focus().insertContent({
            type: 'paragraph',
            content: [{
                type: 'text',
                text: label.trim(),
                marks: [{type: 'link', attrs: {href: url.trim(), class: EMAIL_BUTTON_CLASS}}],
            }],
        }).run();
        close();
    };

    return (
        <>
            <RichTextEditor.Control onClick={() => setOpen(true)} aria-label={t`Insert button`} title={t`Insert button`}>
                <IconSquareRoundedPlus stroke={1.5} size="1rem"/>
            </RichTextEditor.Control>
            <Portal>
                <Modal opened={isOpen} onClose={close} title={t`Insert button`}>
                    <Stack>
                        <TextInput label={t`Button text`} value={label} onChange={event => setLabel(event.currentTarget.value)} maxLength={40} data-autofocus/>
                        <TextInput
                            label={t`Link`}
                            placeholder="https://"
                            value={url}
                            onChange={event => {
                                setUrl(event.currentTarget.value);
                                setUrlError(null);
                            }}
                            error={urlError}
                        />
                        <Group justify="flex-end">
                            <Button variant="default" onClick={close}>{t`Cancel`}</Button>
                            <Button onClick={insert} disabled={!label.trim() || !url.trim()}>{t`Insert button`}</Button>
                        </Group>
                    </Stack>
                </Modal>
            </Portal>
        </>
    );
};
