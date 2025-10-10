import {Anchor, Button} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import classes from "./QrScanner.module.scss";

interface PermissionDeniedMessageProps {
    onRequestPermission: () => void;
    onClose: () => void;
}

export const PermissionDeniedMessage = ({
    onRequestPermission,
    onClose
}: PermissionDeniedMessageProps) => {
    return (
        <div className={classes.permissionMessage}>
            <Trans>
                Camera permission was denied. <Anchor onClick={onRequestPermission}>Request
                Permission</Anchor> again,
                or if this doesn't work,
                you will need to <Anchor target={'_blank'}
                                         href={'https://support.onemob.com/hc/en-us/articles/360037342154-How-do-I-grant-permission-for-Camera-and-Microphone-in-my-web-browser-'}>grant
                this page</Anchor> access to your camera in your browser settings.
            </Trans>

            <div>
                <Button color={'green'} mt={20} onClick={onClose} variant={'filled'}>
                    {t`Close`}
                </Button>
            </div>
        </div>
    );
};