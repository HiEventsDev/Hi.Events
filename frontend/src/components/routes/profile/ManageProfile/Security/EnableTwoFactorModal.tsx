import {Modal} from "@mantine/core";
import {t} from "@lingui/macro";
import {useMediaQuery} from "@mantine/hooks";
import {TwoFactorSetupFlow} from "../../../../common/TwoFactor/TwoFactorSetupFlow.tsx";
import {showSuccess} from "../../../../../utilites/notifications.tsx";
import classes from "./Security.module.scss";

interface EnableTwoFactorModalProps {
    email?: string;
    onClose: () => void;
}

export const EnableTwoFactorModal = ({email, onClose}: EnableTwoFactorModalProps) => {
    const isSmallScreen = useMediaQuery('(max-width: 576px)');

    return (
        <Modal
            opened
            fullScreen={isSmallScreen}
            onClose={onClose}
            title={t`Set up two-factor authentication`}
            size={560}
            radius="lg"
            closeOnClickOutside={false}
            overlayProps={{opacity: 0.55, blur: 3}}
            classNames={{title: classes.modalTitle}}
        >
            <TwoFactorSetupFlow
                email={email}
                onComplete={() => {
                    showSuccess(t`Two-factor authentication is on`);
                    onClose();
                }}
            />
        </Modal>
    );
};
