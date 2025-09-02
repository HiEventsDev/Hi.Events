import {Button, Modal, Stack} from "@mantine/core";
import {IconCamera, IconScan} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {showSuccess} from "../../../utilites/notifications.tsx";

interface ScannerSelectionModalProps {
    isOpen: boolean;
    isHidScannerActive: boolean;
    onClose: () => void;
    onCameraSelect: () => void;
    onHidScannerSelect: () => void;
}

export const ScannerSelectionModal = ({
    isOpen,
    isHidScannerActive,
    onClose,
    onCameraSelect,
    onHidScannerSelect
}: ScannerSelectionModalProps) => {
    return (
        <Modal
            opened={isOpen}
            onClose={onClose}
            title={t`Select Scanner Type`}
            size="sm"
        >
            <Stack>
                <Button
                    leftSection={<IconCamera size={20}/>}
                    onClick={onCameraSelect}
                    fullWidth
                    variant="light"
                >
                    {t`Camera Scanner`}
                </Button>
                <Button
                    leftSection={<IconScan size={20}/>}
                    onClick={() => {
                        onHidScannerSelect();
                        if (!isHidScannerActive) {
                            showSuccess(t`USB Scanner mode activated. Start scanning tickets now.`);
                        }
                    }}
                    fullWidth
                    variant="light"
                    color={isHidScannerActive ? "gray" : undefined}
                    disabled={isHidScannerActive}
                >
                    {isHidScannerActive ? t`USB Scanner Already Active` : t`USB/HID Scanner`}
                </Button>
                <Button
                    onClick={onClose}
                    variant="subtle"
                    fullWidth
                >
                    {t`Cancel`}
                </Button>
            </Stack>
        </Modal>
    );
};