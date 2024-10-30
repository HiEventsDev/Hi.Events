import {useEffect, useRef, useState} from 'react';
import QrScanner from 'qr-scanner';
import {useDebouncedValue} from '@mantine/hooks';
import classes from './QrScanner.module.scss';
import {IconBulb, IconBulbOff, IconCameraRotate, IconVolume, IconVolumeOff, IconX} from "@tabler/icons-react";
import {Anchor, Button, Menu} from "@mantine/core";
import {showError} from "../../../utilites/notifications.tsx";
import {t, Trans} from "@lingui/macro";

interface QRScannerComponentProps {
    onCheckIn: (attendeePublicId: string, onRequestComplete: (didSucceed: boolean) => void, onFailure: () => void) => void;
    onClose: () => void;
}

export const QRScannerComponent = (props: QRScannerComponentProps) => {
    const videoRef = useRef<HTMLVideoElement>(null);
    const qrScannerRef = useRef<QrScanner | null>(null);
    const [permissionGranted, setPermissionGranted] = useState(false);
    const [permissionDenied, setPermissionDenied] = useState(false);
    const [isCheckingIn, setIsCheckingIn] = useState(false);
    const [isFlashAvailable, setIsFlashAvailable] = useState(false);
    const [isFlashOn, setIsFlashOn] = useState(false);
    const [cameraList, setCameraList] = useState<QrScanner.Camera[]>();
    const [processedAttendeeIds, setProcessedAttendeeIds] = useState<string[]>([]);
    const latestProcessedAttendeeIdsRef = useRef<string[]>([]);

    const [currentAttendeeId, setCurrentAttendeeId] = useState<string | null>(null);
    const [debouncedAttendeeId] = useDebouncedValue(currentAttendeeId, 1000);
    const [isScanFailed, setIsScanFailed] = useState(false);
    const [isScanSucceeded, setIsScanSucceeded] = useState(false);

    const scanSuccessAudioRef = useRef<HTMLAudioElement | null>(null);
    const scanErrorAudioRef = useRef<HTMLAudioElement | null>(null);
    const scanInProgressAudioRef = useRef<HTMLAudioElement | null>(null);

    const [isSoundOn, setIsSoundOn] = useState(() => {
        const storedIsSoundOn = localStorage.getItem("qrScannerSoundOn");
        return storedIsSoundOn === null ? true : JSON.parse(storedIsSoundOn);
    });

    useEffect(() => {
        localStorage.setItem("qrScannerSoundOn", JSON.stringify(isSoundOn));
    }, [isSoundOn]);


    useEffect(() => {
        latestProcessedAttendeeIdsRef.current = processedAttendeeIds;
    }, [processedAttendeeIds]);

    const startScanner = async () => {
        try {
            await navigator.mediaDevices.getUserMedia({video: true});
            setPermissionGranted(true);
            if (videoRef.current) {
                qrScannerRef.current = new QrScanner(videoRef.current, (result) => {
                    setCurrentAttendeeId(result.data);
                }, {
                    maxScansPerSecond: 1,
                });
                qrScannerRef.current.start();
            }
        } catch (error) {
            setPermissionDenied(true);
            console.error(error);
        }
    };

    useEffect(() => {
        if (debouncedAttendeeId) {
            const latestProcessedAttendeeIds = latestProcessedAttendeeIdsRef.current;
            const alreadyScanned = latestProcessedAttendeeIds.includes(debouncedAttendeeId);

            if (isScanSucceeded || isScanFailed) {
                return;
            }

            if (alreadyScanned) {
                showError(t`You already scanned this ticket`);

                setIsScanFailed(true);
                setInterval(function () {
                    setIsScanFailed(false);
                }, 500);
                if (isSoundOn && scanErrorAudioRef.current) {
                    scanErrorAudioRef.current.play();
                }

                return;
            }

            if (!isCheckingIn && !alreadyScanned) {
                setIsCheckingIn(true);

                if (isSoundOn && scanInProgressAudioRef.current) {
                    scanInProgressAudioRef.current.play();
                }

                props.onCheckIn(debouncedAttendeeId, (didSucceed) => {
                        setIsCheckingIn(false);
                        setProcessedAttendeeIds(prevIds => [...prevIds, debouncedAttendeeId]);
                        setCurrentAttendeeId(null);

                        if (didSucceed) {
                            setIsScanSucceeded(true);
                            setInterval(function () {
                                setIsScanSucceeded(false);
                            }, 500);
                            if (isSoundOn && scanSuccessAudioRef.current) {
                                scanSuccessAudioRef.current.play();
                            }
                        } else {
                            setIsScanFailed(true);
                            setInterval(function () {
                                setIsScanFailed(false);
                            }, 500);
                            if (isSoundOn && scanErrorAudioRef.current) {
                                scanErrorAudioRef.current.play();
                            }
                        }
                    }, () => {
                        setIsCheckingIn(false);
                        setCurrentAttendeeId(null);
                    }
                );
            }
        }
    }, [debouncedAttendeeId]);

    const stopScanner = () => {
        if (qrScannerRef.current) {
            qrScannerRef.current.stop();
            qrScannerRef.current.destroy();
            qrScannerRef.current = null;
        }
    };

    const handleClose = () => {
        stopScanner();
        props.onClose();
    };

    const handleFlashToggle = () => {
        if (!isFlashAvailable) {
            showError(t`Flash is not available on this device`);
            return;
        }
        if (qrScannerRef.current) {
            if (isFlashOn) {
                qrScannerRef.current.turnFlashOff();
            } else {
                qrScannerRef.current.turnFlashOn();
            }
            setIsFlashOn(!isFlashOn);
        }
    };

    const handleSoundToggle = () => {
        setIsSoundOn(!isSoundOn);
    };

    const requestPermission = async () => {
        setPermissionDenied(false);
        await startScanner();
    };

    const updateFlashAvailability = async () => {
        if (qrScannerRef.current) {
            const hasFlash = await qrScannerRef.current.hasFlash();
            setIsFlashAvailable(hasFlash);
        }
    };

    useEffect(() => {
        startScanner().then(() => {
            updateFlashAvailability().catch(console.error);
            QrScanner.listCameras(true)
                .then(cameras => setCameraList(cameras));
        });

        return () => {
            if (permissionGranted) {
                stopScanner();
            }
        };
    }, []);

    const handleCameraSelection = (camera: QrScanner.Camera) => () => {
        return qrScannerRef.current?.setCamera(camera.id)
            .then(() => updateFlashAvailability().catch(console.error));
    };

    return (
        <div className={classes.videoContainer}>
            {permissionDenied && (
                <div className={classes.permissionMessage}>
                    <Trans>
                        Camera permission was denied. <Anchor onClick={requestPermission}>Request
                        Permission</Anchor> again,
                        or if this doesn't work,
                        you will need to <Anchor target={'_blank'}
                                                 href={'https://support.onemob.com/hc/en-us/articles/360037342154-How-do-I-grant-permission-for-Camera-and-Microphone-in-my-web-browser-'}>grant
                        this page</Anchor> access to your camera in your browser settings.
                    </Trans>

                    <div>
                        <Button color={'green'} mt={20} onClick={handleClose} variant={'filled'}>
                            {t`Close`}
                        </Button>
                    </div>
                </div>
            )}

            <video className={classes.video} ref={videoRef}></video>

            <Button onClick={handleFlashToggle} variant={'transparent'} className={classes.flashToggle}>
                {!isFlashAvailable && <IconBulbOff color={'#ffffff95'} size={30}/>}
                {isFlashAvailable && <IconBulb color={isFlashOn ? 'yellow' : '#ffffff95'} size={30}/>}
            </Button>
            <Button onClick={handleSoundToggle} variant={'transparent'} className={classes.soundToggle}>
                {isSoundOn && <IconVolume color={'#ffffff95'} size={30}/>}
                {!isSoundOn && <IconVolumeOff color={'#ffffff95'} size={30}/>}
            </Button>
            <audio ref={scanSuccessAudioRef} src="/sounds/scan-success.wav"/>
            <audio ref={scanErrorAudioRef} src="/sounds/scan-error.wav"/>
            <audio ref={scanInProgressAudioRef} src="/sounds/scan-in-progress.wav"/>
            <Button onClick={handleClose} variant={'transparent'} className={classes.closeButton}>
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
                            <Menu.Item key={index} onClick={handleCameraSelection(camera)}>
                                {camera.label}
                            </Menu.Item>
                        ))}
                    </Menu.Dropdown>
                </Menu>
            </Button>
            <div className={`${classes.scannerOverlay} ${isScanSucceeded ? classes.success : ""} ${isScanFailed ? classes.failure : ""} ${isCheckingIn ? classes.checkingIn : ""}`}/>
        </div>
    );
};
