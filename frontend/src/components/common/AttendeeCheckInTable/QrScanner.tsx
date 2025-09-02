import {useEffect, useRef, useState} from 'react';
import QrScanner from 'qr-scanner';
import {useDebouncedValue} from '@mantine/hooks';
import classes from './QrScanner.module.scss';
import {showError} from "../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";
import {QrScannerControls} from './QrScannerControls';
import {PermissionDeniedMessage} from './PermissionDeniedMessage';

interface QRScannerComponentProps {
    onAttendeeScanned: (attendeePublicId: string) => void;
    onClose: () => void;
    isSoundOn?: boolean;
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
        // Use the prop value if provided, otherwise fallback to unified storage
        if (props.isSoundOn !== undefined) {
            return props.isSoundOn;
        }
        const storedIsSoundOn = localStorage.getItem("scannerSoundOn");
        return storedIsSoundOn === null ? true : JSON.parse(storedIsSoundOn);
    });

    // Sync with prop changes
    useEffect(() => {
        if (props.isSoundOn !== undefined) {
            setIsSoundOn(props.isSoundOn);
        }
    }, [props.isSoundOn]);

    useEffect(() => {
        // Only save to localStorage if not controlled by props
        if (props.isSoundOn === undefined) {
            localStorage.setItem("scannerSoundOn", JSON.stringify(isSoundOn));
        }
    }, [isSoundOn, props.isSoundOn]);

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
                setInterval(() => setIsScanFailed(false), 500);
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

                props.onAttendeeScanned(debouncedAttendeeId);
                setIsCheckingIn(false);
                setProcessedAttendeeIds(prevIds => [...prevIds, debouncedAttendeeId]);
                setCurrentAttendeeId(null);

                setIsScanSucceeded(true);
                setInterval(() => setIsScanSucceeded(false), 500);
                if (isSoundOn && scanSuccessAudioRef.current) {
                    scanSuccessAudioRef.current.play();
                }
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

    const handleCameraSelection = (camera: QrScanner.Camera) => {
        return qrScannerRef.current?.setCamera(camera.id)
            .then(() => updateFlashAvailability().catch(console.error));
    };

    return (
        <div className={classes.videoContainer}>
            {permissionDenied && (
                <PermissionDeniedMessage
                    onRequestPermission={requestPermission}
                    onClose={handleClose}
                />
            )}

            <video className={classes.video} ref={videoRef}></video>

            <QrScannerControls
                isFlashAvailable={isFlashAvailable}
                isFlashOn={isFlashOn}
                isSoundOn={isSoundOn}
                cameraList={cameraList}
                onFlashToggle={handleFlashToggle}
                onSoundToggle={handleSoundToggle}
                onCameraSelect={handleCameraSelection}
                onClose={handleClose}
            />

            <audio ref={scanSuccessAudioRef} src="/sounds/scan-success.wav"/>
            <audio ref={scanErrorAudioRef} src="/sounds/scan-error.wav"/>
            <audio ref={scanInProgressAudioRef} src="/sounds/scan-in-progress.wav"/>
            
            <div className={`${classes.scannerOverlay} ${isScanSucceeded ? classes.success : ""} ${isScanFailed ? classes.failure : ""} ${isCheckingIn ? classes.checkingIn : ""}`}/>
        </div>
    );
};
