import {useEffect, useRef, useState} from "react";
import QrScanner from "qr-scanner";
import {useDebouncedValue} from "@mantine/hooks";
import {t, Trans} from "@lingui/macro";
import {Anchor, Button, Menu} from "@mantine/core";
import {IconBulb, IconBulbOff, IconCameraRotate} from "@tabler/icons-react";
import classes from "./InlineCameraScanner.module.scss";

interface Props {
    onAttendeeScanned: (attendeePublicId: string) => void;
}

export const InlineCameraScanner = ({onAttendeeScanned}: Props) => {
    const videoRef = useRef<HTMLVideoElement>(null);
    const qrScannerRef = useRef<QrScanner | null>(null);
    const [permissionDenied, setPermissionDenied] = useState(false);
    const [isFlashAvailable, setIsFlashAvailable] = useState(false);
    const [isFlashOn, setIsFlashOn] = useState(false);
    const [cameraList, setCameraList] = useState<QrScanner.Camera[]>();
    const [processed, setProcessed] = useState<string[]>([]);
    const latestProcessedRef = useRef<string[]>([]);

    const [currentId, setCurrentId] = useState<string | null>(null);
    const [debouncedId] = useDebouncedValue(currentId, 1000);

    useEffect(() => {
        latestProcessedRef.current = processed;
    }, [processed]);

    const startScanner = async () => {
        try {
            await navigator.mediaDevices.getUserMedia({video: true});
            if (videoRef.current) {
                qrScannerRef.current = new QrScanner(videoRef.current, (result) => {
                    setCurrentId(result.data);
                }, {
                    maxScansPerSecond: 1,
                    highlightScanRegion: false,
                    highlightCodeOutline: false,
                });
                qrScannerRef.current.start();
            }
        } catch (error) {
            setPermissionDenied(true);
            console.error(error);
        }
    };

    const stopScanner = () => {
        if (qrScannerRef.current) {
            qrScannerRef.current.stop();
            qrScannerRef.current.destroy();
            qrScannerRef.current = null;
        }
    };

    useEffect(() => {
        startScanner().then(async () => {
            if (qrScannerRef.current) {
                const hasFlash = await qrScannerRef.current.hasFlash();
                setIsFlashAvailable(hasFlash);
            }
            const cameras = await QrScanner.listCameras(true);
            setCameraList(cameras);
        });
        return () => {
            stopScanner();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        if (!debouncedId) return;
        if (latestProcessedRef.current.includes(debouncedId)) return;
        onAttendeeScanned(debouncedId);
        setProcessed(prev => [...prev, debouncedId]);
        setCurrentId(null);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [debouncedId]);

    const handleFlashToggle = () => {
        if (!qrScannerRef.current || !isFlashAvailable) return;
        if (isFlashOn) {
            qrScannerRef.current.turnFlashOff();
        } else {
            qrScannerRef.current.turnFlashOn();
        }
        setIsFlashOn(!isFlashOn);
    };

    const handleCameraSelect = (camera: QrScanner.Camera) => {
        qrScannerRef.current?.setCamera(camera.id)
            .then(async () => {
                if (qrScannerRef.current) {
                    const hasFlash = await qrScannerRef.current.hasFlash();
                    setIsFlashAvailable(hasFlash);
                }
            });
    };

    const requestPermission = async () => {
        setPermissionDenied(false);
        await startScanner();
    };

    if (permissionDenied) {
        return (
            <div className={classes.permissionDenied}>
                <p>
                    <Trans>
                        Camera permission was denied. <Anchor onClick={requestPermission}>Request permission</Anchor> again,
                        or grant this page camera access in your browser settings.
                    </Trans>
                </p>
            </div>
        );
    }

    return (
        <div className={classes.scanner}>
            <video ref={videoRef} className={classes.video} playsInline/>

            <div className={classes.reticle}>
                <span className={`${classes.corner} ${classes.tl}`}/>
                <span className={`${classes.corner} ${classes.tr}`}/>
                <span className={`${classes.corner} ${classes.bl}`}/>
                <span className={`${classes.corner} ${classes.br}`}/>
            </div>

            <div className={classes.controls}>
                {isFlashAvailable && (
                    <Button
                        onClick={handleFlashToggle}
                        variant="default"
                        size="compact-sm"
                        radius="xl"
                        className={classes.control}
                        leftSection={isFlashOn ? <IconBulb size={14}/> : <IconBulbOff size={14}/>}
                    >
                        {isFlashOn ? t`Flash on` : t`Flash off`}
                    </Button>
                )}
                {cameraList && cameraList.length > 1 && (
                    <Menu shadow="md" width={220}>
                        <Menu.Target>
                            <Button
                                variant="default"
                                size="compact-sm"
                                radius="xl"
                                className={classes.control}
                                leftSection={<IconCameraRotate size={14}/>}
                            >
                                {t`Camera`}
                            </Button>
                        </Menu.Target>
                        <Menu.Dropdown>
                            <Menu.Label>{t`Select camera`}</Menu.Label>
                            {cameraList.map((camera, index) => (
                                <Menu.Item key={index} onClick={() => handleCameraSelect(camera)}>
                                    {camera.label}
                                </Menu.Item>
                            ))}
                        </Menu.Dropdown>
                    </Menu>
                )}
            </div>
        </div>
    );
};
