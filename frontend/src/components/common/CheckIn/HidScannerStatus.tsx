import {Button} from "@mantine/core";
import {IconScan, IconX} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {showSuccess} from "../../../utilites/notifications.tsx";

interface HidScannerStatusProps {
    isActive: boolean;
    pageHasFocus: boolean;
    onDisable: () => void;
}

export const HidScannerStatus = ({
    isActive,
    pageHasFocus,
    onDisable
}: HidScannerStatusProps) => {
    if (!isActive) return null;

    return (
        <div style={{
            backgroundColor: pageHasFocus ? '#12b886' : '#ffa94d',
            color: 'white',
            padding: '8px 16px',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            fontSize: '14px',
            fontWeight: 500,
            transition: 'background-color 0.2s ease',
            marginTop: '-1px',
        }}>
            <div style={{display: 'flex', alignItems: 'center', gap: '8px'}}>
                <IconScan size={18}/>
                <span>
                    {pageHasFocus
                        ? 'USB Scanner Active - Ready to Scan'
                        : 'USB Scanner Paused - Click anywhere to resume scanning'}
                </span>
            </div>
            <Button
                size="xs"
                variant="white"
                color={pageHasFocus ? "teal" : "orange"}
                leftSection={<IconX size={14}/>}
                miw={95}
                onClick={() => {
                    onDisable();
                    showSuccess(t`USB Scanner mode deactivated`);
                }}
            >
                Disable
            </Button>
        </div>
    );
};
