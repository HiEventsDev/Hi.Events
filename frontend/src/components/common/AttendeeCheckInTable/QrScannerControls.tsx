import {Button, Menu} from "@mantine/core";
import {IconBulb, IconBulbOff, IconCameraRotate, IconVolume, IconVolumeOff, IconX} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import QrScanner from "qr-scanner";
import classes from "./QrScanner.module.scss";

interface QrScannerControlsProps {
    isFlashAvailable: boolean;
    isFlashOn: boolean;
    isSoundOn: boolean;
    cameraList: QrScanner.Camera[] | undefined;
    onFlashToggle: () => void;
    onSoundToggle: () => void;
    onCameraSelect: (camera: QrScanner.Camera) => void;
    onClose: () => void;
}

export const QrScannerControls = ({
    isFlashAvailable,
    isFlashOn,
    isSoundOn,
    cameraList,
    onFlashToggle,
    onSoundToggle,
    onCameraSelect,
    onClose
}: QrScannerControlsProps) => {
    return (
        <>
            <Button onClick={onFlashToggle} variant={'transparent'} className={classes.flashToggle}>
                {!isFlashAvailable && <IconBulbOff color={'#ffffff95'} size={30}/>}
                {isFlashAvailable && <IconBulb color={isFlashOn ? 'yellow' : '#ffffff95'} size={30}/>}
            </Button>
            <Button onClick={onSoundToggle} variant={'transparent'} className={classes.soundToggle}>
                {isSoundOn && <IconVolume color={'#ffffff95'} size={30}/>}
                {!isSoundOn && <IconVolumeOff color={'#ffffff95'} size={30}/>}
            </Button>
            <Button onClick={onClose} variant={'transparent'} className={classes.closeButton}>
                <IconX color={'#ffffff95'} size={30}/>
            </Button>
            <Button variant={'transparent'} className={classes.switchCameraButton}>
                <Menu shadow="md" width={200}>
                    <Menu.Target>
                        <IconCameraRotate color={'#ffffff95'} size={30}/>
                    </Menu.Target>
                    <Menu.Dropdown>
                        <Menu.Label>{t`Select Camera`}</Menu.Label>
                        {cameraList?.map((camera, index) => (
                            <Menu.Item key={index} onClick={() => onCameraSelect(camera)}>
                                {camera.label}
                            </Menu.Item>
                        ))}
                    </Menu.Dropdown>
                </Menu>
            </Button>
        </>
    );
};