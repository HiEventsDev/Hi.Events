import {Button, Modal} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconInfoCircle} from "@tabler/icons-react";

interface Props {
    isOpen: boolean;
    description: string | null | undefined;
    onDismiss: () => void;
}

export const CheckInDescriptionModal = ({isOpen, description, onDismiss}: Props) => {
    if (!description) return null;

    return (
        <Modal
            opened={isOpen}
            onClose={onDismiss}
            title={
                <div style={{display: "flex", alignItems: "center", gap: 8, fontWeight: 700}}>
                    <IconInfoCircle size={18}/> {t`Door staff instructions`}
                </div>
            }
            size="md"
            centered
            radius="md"
            padding={24}
        >
            <div
                style={{
                    fontSize: 15,
                    lineHeight: 1.55,
                    color: "var(--hi-text)",
                    whiteSpace: "pre-wrap",
                    marginBottom: 20,
                }}
            >
                {description}
            </div>
            <Button fullWidth size="md" onClick={onDismiss}>
                {t`Got it`}
            </Button>
        </Modal>
    );
};
