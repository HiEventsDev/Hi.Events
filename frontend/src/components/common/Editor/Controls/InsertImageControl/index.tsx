import {RichTextEditor, useRichTextEditorContext} from "@mantine/tiptap";
import {useCallback, useState} from "react";
import {t} from "@lingui/macro";
import {IconPhotoPlus} from "@tabler/icons-react";
import {Button, Group, Modal, Portal, TextInput} from "@mantine/core";

export const InsertImageControl = () => {
    const editor = useRichTextEditorContext();
    const [isModalOpen, setModalOpen] = useState(false);
    const [imageUrl, setImageUrl] = useState('');
    const [imageError, setImageError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);

    // Function to validate if the URL is an actual image
    const checkImageExists = (url: string) => {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => resolve(true);
            img.onerror = () => reject(false);
            img.src = url;
        });
    };

    const handleImageInsert = useCallback(async () => {
        setLoading(true);
        try {
            await checkImageExists(imageUrl);
            if (editor) {
                editor.editor!.commands.setImage({src: imageUrl});
            }
            setModalOpen(false);
            setImageError(null);
            setImageUrl('');
        } catch {
            setImageError(t`Please enter a valid image URL that points to an image.`);
        } finally {
            setLoading(false);
        }
    }, [editor, imageUrl]);

    return (
        <>
            <RichTextEditor.Control
                onClick={() => setModalOpen(true)}
                aria-label="Insert star emoji"
                title="Insert star emoji"
            >
                <IconPhotoPlus stroke={1.5} size="1rem"/>
            </RichTextEditor.Control>

            <Portal>
                <Modal
                    opened={isModalOpen}
                    onClose={() => setModalOpen(false)}
                    title={t`Insert Image`}
                >
                    <TextInput
                        label={t`Image URL`}
                        placeholder="https://example.com/image.jpg"
                        value={imageUrl}
                        onChange={(event) => setImageUrl(event.currentTarget.value)}
                        error={imageError}
                    />
                    <Group mt="md">
                        <Button onClick={handleImageInsert} loading={loading}>
                            {t`Insert Image`}
                        </Button>
                    </Group>
                </Modal>
            </Portal>
        </>
    );
};
