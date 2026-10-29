import {createContext, ReactNode, useCallback, useContext, useEffect, useRef, useState} from "react";
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
        iframeRef.current.contentWindow.postMessage({type: messageType, settings}, window.location.origin);
        lastSentSettings.current = settingsJson;
    }, [loaded, settingsJson]);

    const onLoad = () => {
        lastSentSettings.current = null;
        setLoaded(true);
    };

    return {iframeRef, src, onLoad};
};

export const firstHomepageSectionsError = (error: any): string | undefined => {
    const errors: Record<string, string[] | string> | undefined = error?.response?.data?.errors;
    const field = errors && Object.keys(errors).find(key => key.startsWith('homepage_sections'));
    if (!field) {
        return undefined;
    }
    const messages = errors[field];
    return Array.isArray(messages) ? messages[0] : messages;
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

type SaveGuard = () => boolean;

const SaveGuardContext = createContext<(guard: SaveGuard) => () => void>(() => () => undefined);

export const useHomepageSaveGuard = (blocksSave: SaveGuard) => {
    const register = useContext(SaveGuardContext);
    useEffect(() => register(blocksSave), [register, blocksSave]);
};

export const HomepageDesigner = ({preview, previewTitle, onSave, ...shellProps}: HomepageDesignerProps) => {
    const [device, setDevice] = useState<PreviewDevice>('desktop');
    const [saveGuards] = useState(() => new Set<SaveGuard>());

    const registerSaveGuard = useCallback((guard: SaveGuard) => {
        saveGuards.add(guard);
        return () => {
            saveGuards.delete(guard);
        };
    }, [saveGuards]);

    const save = () => {
        if (Array.from(saveGuards).some(blocksSave => blocksSave())) {
            return;
        }
        onSave();
    };

    return (
        <SaveGuardContext.Provider value={registerSaveGuard}>
            <DesignerShell
                {...shellProps}
                onSave={save}
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
        </SaveGuardContext.Provider>
    );
};
