import {RichTextEditor, useRichTextEditorContext} from "@mantine/tiptap";
import {useCallback, useState} from "react";
import {t} from "@lingui/macro";
import {IconBrandYoutube} from "@tabler/icons-react";
import {Button, Group, Modal, Portal, TextInput} from "@mantine/core";

export const InsertIframeControl = () => {
    const editor = useRichTextEditorContext();
    const [isModalOpen, setModalOpen] = useState(false);
    const [iframeUrl, setIframeUrl] = useState('');
    const [iframeWidth, setIframeWidth] = useState('100%');
    const [iframeHeight, setIframeHeight] = useState('315');
    const [iframeTitle, setIframeTitle] = useState('');
    const [urlError, setUrlError] = useState<string | null>(null);

    const validateUrl = (url: string) => {
        // Basic URL validation
        try {
            new URL(url);
            return true;
        } catch (e) {
            return false;
        }
    };

    const handleIframeInsert = useCallback(() => {
        if (!iframeUrl || !validateUrl(iframeUrl)) {
            setUrlError(t`Please enter a valid URL`);
            return;
        }

        if (editor && editor.editor) {
            editor.editor.commands.setIframe({
                src: iframeUrl,
                width: iframeWidth,
                height: `${iframeHeight}px`,
                title: iframeTitle || undefined
            });

            // Reset and close modal
            setModalOpen(false);
            setIframeUrl('');
            setIframeWidth('100%');
            setIframeHeight('315');
            setIframeTitle('');
            setUrlError(null);
        }
    }, [editor, iframeUrl, iframeWidth, iframeHeight, iframeTitle]);

    return (
        <>
            <RichTextEditor.Control
                onClick={() => setModalOpen(true)}
                aria-label="Insert iframe"
                title="Insert iframe"
            >
                <IconBrandYoutube stroke={1.5} size="1rem"/>
            </RichTextEditor.Control>

            <Portal>
                <Modal
                    opened={isModalOpen}
                    onClose={() => setModalOpen(false)}
                    title={t`Insert Iframe`}
                >
                    <TextInput
                        label={t`Iframe URL`}
                        placeholder="https://www.youtube.com/embed/dQw4w9WgXcQ"
                        value={iframeUrl}
                        onChange={(event) => {
                            setIframeUrl(event.currentTarget.value);
                            setUrlError(null);
                        }}
                        error={urlError}
                        required
                        mb="md"
                    />

                    <TextInput
                        label={t`Width`}
                        placeholder="100%"
                        value={iframeWidth}
                        onChange={(event) => setIframeWidth(event.currentTarget.value)}
                        mb="md"
                    />

                    <TextInput
                        label={t`Height (px)`}
                        placeholder="315"
                        value={iframeHeight}
                        onChange={(event) => setIframeHeight(event.currentTarget.value)}
                        mb="md"
                    />

                    <TextInput
                        label={t`Title (for accessibility)`}
                        placeholder="Video title"
                        value={iframeTitle}
                        onChange={(event) => setIframeTitle(event.currentTarget.value)}
                        mb="md"
                    />

                    <Group mt="md">
                        <Button onClick={handleIframeInsert}>
                            {t`Insert Iframe`}
                        </Button>
                    </Group>
                </Modal>
            </Portal>
        </>
    );
};
