import {useEffect, useState} from "react";
import {Button, TextInput} from "@mantine/core";
import {t} from "@lingui/macro";
import {Modal, ModalFooter} from "../Modal";

interface NameModalProps {
    opened: boolean;
    initialName: string;
    title: string;
    submitLabel: string;
    loading: boolean;
    onClose: () => void;
    onSubmit: (name: string) => void;
}

export const NameModal = ({opened, initialName, title, submitLabel, loading, onClose, onSubmit}: NameModalProps) => {
    const [name, setName] = useState(initialName);

    useEffect(() => {
        if (opened) {
            setName(initialName);
        }
    }, [opened, initialName]);

    return (
        <Modal opened={opened} onClose={onClose} heading={title} size="md">
            <form onSubmit={(event) => {
                event.preventDefault();
                if (name.trim()) {
                    onSubmit(name.trim());
                }
            }}>
                <TextInput
                    label={t`Name`}
                    description={t`Only your team sees this. Use it to tell widgets apart, like "Homepage" or "Newsletter".`}
                    value={name}
                    maxLength={100}
                    onChange={(event) => setName(event.currentTarget.value)}
                    data-autofocus
                    required
                />
                <ModalFooter>
                    <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                    <Button type="submit" loading={loading} disabled={!name.trim()} data-testid="widget-name-submit-button">
                        {submitLabel}
                    </Button>
                </ModalFooter>
            </form>
        </Modal>
    );
};
