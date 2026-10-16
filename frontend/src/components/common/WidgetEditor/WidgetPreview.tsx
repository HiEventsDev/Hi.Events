import {CSSProperties, ReactNode, useEffect, useRef, useState} from "react";
import {t} from "@lingui/macro";
import {IconX} from "@tabler/icons-react";
import {PreviewDevice} from "../DesignerShell";
import {LoadingMask} from "../LoadingMask";
import {WidgetSettings, WidgetType} from "../../../utilites/widgetConfig.ts";
import {PREVIEW_OPEN_EVENT_MESSAGE, PREVIEW_READY_MESSAGE, PREVIEW_SETTINGS_MESSAGE} from "../../widget/useWidgetRuntime.ts";
import {WidgetTarget} from "./embedCode.ts";
import classes from './WidgetEditor.module.scss';

const PREVIEW_IFRAME_ID = 'hi-widget-preview';
const MODAL_IFRAME_ID = 'hi-widget-preview-modal';

const widgetPath = (target: WidgetTarget, type: WidgetType) => {
    if ('organizerId' in target) {
        return `/widget/organizer/${target.organizerId}`;
    }
    const suffix = type === 'LINEUP' ? '/lineup' : type === 'SCHEDULE' ? '/schedule' : '';
    return `/widget/${target.eventId}${suffix}`;
};

interface PreviewIframeProps {
    src: string;
    iframeId: string;
    settings: WidgetSettings;
    title: string;
}

const PreviewIframe = ({src, iframeId, settings, title}: PreviewIframeProps) => {
    const iframeRef = useRef<HTMLIFrameElement>(null);
    const settingsRef = useRef(settings);
    settingsRef.current = settings;
    const [ready, setReady] = useState(false);
    const [height, setHeight] = useState<number | null>(null);

    useEffect(() => {
        setReady(false);
        setHeight(null);
    }, [src]);

    useEffect(() => {
        const onMessage = (event: MessageEvent) => {
            if (event.origin !== window.location.origin || event.source !== iframeRef.current?.contentWindow) {
                return;
            }
            if (event.data?.type === PREVIEW_READY_MESSAGE) {
                setReady(true);
                iframeRef.current?.contentWindow?.postMessage({type: PREVIEW_SETTINGS_MESSAGE, settings: settingsRef.current}, window.location.origin);
            }
            if (event.data?.type === 'resize' && event.data.iframeId === iframeId) {
                setHeight(Math.max(80, Math.min(Number(event.data.height) || 0, 20000)));
            }
        };
        window.addEventListener('message', onMessage);
        return () => window.removeEventListener('message', onMessage);
    }, [iframeId]);

    useEffect(() => {
        if (ready) {
            iframeRef.current?.contentWindow?.postMessage({type: PREVIEW_SETTINGS_MESSAGE, settings}, window.location.origin);
        }
    }, [ready, settings]);

    const style: CSSProperties = {height: height ? `${height}px` : '320px'};

    return (
        <div className={classes.previewIframeWrapper} data-loading={height === null ? '' : undefined}>
            {height === null && <div className={classes.previewIframeLoading}><LoadingMask/></div>}
            <iframe ref={iframeRef} src={src} title={title} className={classes.previewIframe} style={style}/>
        </div>
    );
};

const HostPage = ({device, overlay, children}: { device: PreviewDevice; overlay?: ReactNode; children: ReactNode }) => (
    <>
        <div className={classes.stage}>
            <div className={device === 'mobile' ? classes.phone : classes.browser}>
                <div className={classes.browserBar}>
                    {device === 'desktop' && (
                        <div className={classes.browserDots} aria-hidden>
                            <span/><span/><span/>
                        </div>
                    )}
                    <div className={classes.addressBar}>{t`your-website.com`}</div>
                </div>
                <div className={classes.site}>
                    <div className={classes.siteNav} aria-hidden>
                        <span className={classes.siteLogo}/>
                        <span className={classes.siteLinks}>
                            <span/><span/><span/>
                        </span>
                    </div>
                    <div className={classes.siteBody}>
                        <div className={classes.siteIntro} aria-hidden>
                            <span className={classes.siteHeading}/>
                            <span className={classes.siteLine}/>
                            <span className={classes.siteLineShort}/>
                        </div>
                        {children}
                        <div className={classes.siteIntro} aria-hidden>
                            <span className={classes.siteLine}/>
                            <span className={classes.siteLine}/>
                            <span className={classes.siteLineShort}/>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        {overlay}
    </>
);

interface WidgetPreviewProps {
    target: WidgetTarget;
    type: WidgetType;
    settings: WidgetSettings;
    device: PreviewDevice;
}

const PreviewModal = ({path, settings, device, onClose}: {
    path: string;
    settings: WidgetSettings;
    device: PreviewDevice;
    onClose: () => void;
}) => (
    <div className={classes.previewModalBackdrop} onClick={onClose}>
        <div className={classes.previewModal} data-device={device} onClick={(event) => event.stopPropagation()} data-testid="widget-preview-modal">
            <button type="button" className={classes.previewModalClose} onClick={onClose} aria-label={t`Close`}>
                <IconX size={18}/>
            </button>
            <PreviewIframe
                src={`${path}?Preview=true&Mode=modal&iframeId=${MODAL_IFRAME_ID}`}
                iframeId={MODAL_IFRAME_ID}
                settings={settings}
                title={t`Widget preview`}
            />
        </div>
    </div>
);

export const WidgetPreview = ({target, type, settings, device}: WidgetPreviewProps) => {
    const [modalPath, setModalPath] = useState<string | null>(null);
    const path = widgetPath(target, type);
    const src = `${path}?Preview=true&iframeId=${PREVIEW_IFRAME_ID}`;

    useEffect(() => {
        setModalPath(null);
    }, [type]);

    useEffect(() => {
        const onMessage = (event: MessageEvent) => {
            if (event.origin === window.location.origin && event.data?.type === PREVIEW_OPEN_EVENT_MESSAGE && event.data.eventId) {
                setModalPath(`/widget/${encodeURIComponent(event.data.eventId)}`);
            }
        };
        window.addEventListener('message', onMessage);
        return () => window.removeEventListener('message', onMessage);
    }, []);

    const modal = modalPath && (
        <PreviewModal path={modalPath} settings={settings} device={device} onClose={() => setModalPath(null)}/>
    );

    if (type === 'BUTTON') {
        const buttonStyle = {
            background: settings.secondary_color || 'var(--mantine-primary-color-filled)',
            color: settings.secondary_text_color || '#ffffff',
            borderRadius: Number.isFinite(settings.radius) ? `${settings.radius}px` : '10px',
        };

        return (
            <HostPage device={device} overlay={modal}>
                <div className={classes.buttonSlot}>
                    <button type="button" className={classes.previewButton} style={buttonStyle} onClick={() => setModalPath(path)}>
                        {settings.button_text || t`Get tickets`}
                    </button>
                    <span className={classes.buttonHint}>{t`Click the button to preview the pop-up`}</span>
                </div>
            </HostPage>
        );
    }

    return (
        <HostPage device={device} overlay={modal}>
            <div className={classes.widgetSlot}>
                <PreviewIframe src={src} iframeId={PREVIEW_IFRAME_ID} settings={settings} title={t`Widget preview`}/>
            </div>
        </HostPage>
    );
};
