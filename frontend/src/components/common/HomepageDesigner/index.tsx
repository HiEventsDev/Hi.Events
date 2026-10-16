import {ReactNode, useEffect, useRef, useState} from "react";
import {LoadingMask} from "../LoadingMask";
import {DesignerShell, PreviewDevice, PreviewDeviceToggle} from "../DesignerShell";
import classes from "./HomepageDesigner.module.scss";

interface UseHomepagePreviewOptions {
    path: string;
    ready: boolean;
    imageVersion: string;
    messageType: string;
    settings: object;
}

export const useHomepagePreview = ({path, ready, imageVersion, messageType, settings}: UseHomepagePreviewOptions) => {
    const iframeRef = useRef<HTMLIFrameElement>(null);
    const lastSentSettings = useRef<string | null>(null);
    const loadedImageVersion = useRef<string | null>(null);
    const [src, setSrc] = useState<string | null>(null);
    const [loaded, setLoaded] = useState(false);

    useEffect(() => {
        if (!ready) {
            return;
        }
        if (loadedImageVersion.current === null) {
            loadedImageVersion.current = imageVersion;
            setSrc(path);
            return;
        }
        if (loadedImageVersion.current !== imageVersion) {
            loadedImageVersion.current = imageVersion;
            setSrc(`${path}?${imageVersion}`);
            setLoaded(false);
        }
    }, [ready, imageVersion, path]);

    const settingsJson = JSON.stringify(settings);

    useEffect(() => {
        if (!loaded || !iframeRef.current?.contentWindow || settingsJson === lastSentSettings.current) {
            return;
        }
        iframeRef.current.contentWindow.postMessage({type: messageType, settings}, "*");
        lastSentSettings.current = settingsJson;
    }, [loaded, settingsJson]);

    const onLoad = () => {
        lastSentSettings.current = null;
        setLoaded(true);
    };

    return {iframeRef, src, onLoad};
};

interface HomepageDesignerProps {
    title: string;
    preview: ReturnType<typeof useHomepagePreview>;
    previewTitle: string;
    hasUnsavedChanges: boolean;
    isSaving: boolean;
    onSave: () => void;
    onDiscard: () => void;
    children: ReactNode;
}

export const HomepageDesigner = ({preview, previewTitle, ...shellProps}: HomepageDesignerProps) => {
    const [device, setDevice] = useState<PreviewDevice>('desktop');

    return (
        <DesignerShell
            {...shellProps}
            previewActions={(
                <PreviewDeviceToggle value={device} onChange={setDevice}/>
            )}
            preview={(
                <div className={device === 'mobile' ? classes.mobileFrame : classes.desktopFrame}>
                    {preview.src ? (
                        <iframe
                            ref={preview.iframeRef}
                            src={preview.src}
                            title={previewTitle}
                            onLoad={preview.onLoad}
                        />
                    ) : (
                        <LoadingMask/>
                    )}
                </div>
            )}
        />
    );
};
