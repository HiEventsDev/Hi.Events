import {useState} from 'react';
import {Button, CopyButton, Textarea, TextInput, Tooltip, UnstyledButton} from '@mantine/core';
import {IconCheck, IconCopy, IconDots, IconDownload, IconPhotoPlus} from '@tabler/icons-react';
import QRCode from 'react-qr-code';
import {t} from "@lingui/macro";
import {NavLink} from "react-router";
import classNames from "classnames";
import {Event, Organizer} from "../../../types.ts";
import {Modal} from "../../common/Modal";
import {WriteAccess} from "../../common/WriteAccess";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {eventSharePreview, organizerSharePreview, SharePreviewContent} from "../../../utilites/sharePreview.ts";
import {SharePreview} from "./SharePreview.tsx";
import {CaptionSuggestions} from "./CaptionSuggestions.tsx";
import {SHARE_PLATFORMS, SharePlatform, X_POST_CAPTION_LIMIT} from "./platforms.ts";
import classes from './ShareModal.module.scss';

interface ShareModalProps {
    opened: boolean;
    onClose: () => void;
    url: string;
    title: string;
    modalTitle?: string;
    shareText?: string;
    event?: Event;
    organizer?: Organizer;
    suggestCaptions?: boolean;
}

const QR_CODE_ELEMENT_ID = 'share-qr-code';

const resolvePreview = (title: string, event?: Event, organizer?: Organizer): SharePreviewContent => {
    if (event) {
        return eventSharePreview(event);
    }

    if (organizer) {
        return organizerSharePreview(organizer);
    }

    return {title, description: ''};
};

export const ShareModal = ({
    opened,
    onClose,
    url,
    title,
    modalTitle = t`Share`,
    shareText,
    event,
    organizer,
    suggestCaptions = false,
}: ShareModalProps) => {
    const [caption, setCaption] = useState(shareText || title);
    const preview = resolvePreview(title, event, organizer);
    const authorName = event?.organizer?.name || organizer?.name || title;
    const canShareNatively = typeof navigator !== 'undefined' && 'share' in navigator;
    const isTooLongForX = caption.length > X_POST_CAPTION_LIMIT;

    const shareNatively = async () => {
        try {
            await navigator.share({title, text: caption, url});
        } catch (error) {
            if ((error as DOMException)?.name !== 'AbortError') {
                console.error('Share failed:', error);
            }
        }
    };

    const shareTo = (platform: SharePlatform) => {
        const shareUrl = platform.shareUrl(encodeURIComponent(caption), encodeURIComponent(url));

        if (!platform.carriesCaption && caption.trim() !== '') {
            navigator.clipboard?.writeText(caption)
                .then(() => showSuccess(t`Caption copied. Paste it into your ${platform.name} post.`))
                .catch(() => undefined);
        }

        if (platform.opensMailClient) {
            window.location.href = shareUrl;
            return;
        }

        window.open(shareUrl, '_blank', 'width=600,height=400');
    };

    const downloadQrCode = () => {
        const svg = document.getElementById(QR_CODE_ELEMENT_ID);

        if (!svg) {
            return;
        }

        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');
        const image = new Image();

        image.onload = () => {
            canvas.width = image.width;
            canvas.height = image.height;
            context?.drawImage(image, 0, 0);

            const downloadLink = document.createElement('a');
            downloadLink.download = `${title}-qr-code.png`;
            downloadLink.href = canvas.toDataURL('image/png');
            downloadLink.click();
        };

        const svgData = new XMLSerializer().serializeToString(svg);
        image.src = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(svgData)));
    };

    return (
        <Modal
            opened={opened}
            onClose={onClose}
            heading={modalTitle}
            centered
            size={940}
        >
            <div className={classes.layout}>
                <div className={classes.previewPane}>
                    <SharePreview content={preview} caption={caption} url={url} authorName={authorName}/>

                    {event && !preview.imageUrl && (
                        <div className={classes.coverPrompt} data-testid="share-add-cover-prompt">
                            <IconPhotoPlus size={18} stroke={1.5} aria-hidden/>
                            <div>
                                <div className={classes.coverPromptTitle}>{t`This event has no cover image`}</div>
                                <div className={classes.coverPromptText}>
                                    {t`Shared links show the cover image, so links without one are easy to scroll past.`}
                                </div>
                                <WriteAccess>
                                    <Button
                                        component={NavLink}
                                        to={`/manage/event/${event.id}/homepage-designer`}
                                        onClick={onClose}
                                        size="compact-sm"
                                        variant="default"
                                        mt={8}
                                    >
                                        {t`Add a cover image`}
                                    </Button>
                                </WriteAccess>
                            </div>
                        </div>
                    )}
                </div>

                <div className={classes.controls}>
                    <section className={classes.section}>
                        <Textarea
                            label={t`Caption`}
                            value={caption}
                            onChange={(changeEvent) => setCaption(changeEvent.currentTarget.value)}
                            autosize
                            minRows={3}
                            maxRows={7}
                            data-testid="share-caption-input"
                        />
                        <div className={classNames(classes.captionMeta, isTooLongForX && classes.captionMetaWarning)}>
                            {isTooLongForX
                                ? t`${caption.length} characters, too long for an X post`
                                : t`${caption.length} characters`}
                        </div>

                        {suggestCaptions && event && (
                            <CaptionSuggestions event={event} caption={caption} onPick={setCaption}/>
                        )}
                    </section>

                    <section className={classes.section}>
                        <span className={classes.sectionLabel}>{t`Share to`}</span>
                        <div className={classes.platforms}>
                            {SHARE_PLATFORMS.map((platform) => (
                                <Tooltip key={platform.name} label={platform.name} withArrow openDelay={200}>
                                    <UnstyledButton
                                        className={classes.platformButton}
                                        onClick={() => shareTo(platform)}
                                        aria-label={t`Share to ${platform.name}`}
                                        style={{color: platform.color}}
                                    >
                                        <platform.icon size={20} stroke={1.6}/>
                                    </UnstyledButton>
                                </Tooltip>
                            ))}
                            {canShareNatively && (
                                <Tooltip label={t`More options`} withArrow openDelay={200}>
                                    <UnstyledButton
                                        className={classes.platformButton}
                                        onClick={shareNatively}
                                        aria-label={t`More options`}
                                    >
                                        <IconDots size={20} stroke={1.6}/>
                                    </UnstyledButton>
                                </Tooltip>
                            )}
                        </div>
                    </section>

                    <section className={classes.section}>
                        <div className={classes.linkRow}>
                            <div className={classes.qrCode}>
                                <QRCode id={QR_CODE_ELEMENT_ID} value={url} size={256} level="H"/>
                            </div>
                            <div className={classes.linkFields}>
                                <TextInput
                                    label={t`Link`}
                                    value={url}
                                    readOnly
                                    onFocus={(focusEvent) => focusEvent.currentTarget.select()}
                                    data-testid="share-link-input"
                                />
                                <div className={classes.linkActions}>
                                    <CopyButton value={url} timeout={2000}>
                                        {({copied, copy}) => (
                                            <Button
                                                size="compact-sm"
                                                variant="default"
                                                onClick={copy}
                                                leftSection={copied ? <IconCheck size={14}/> : <IconCopy size={14}/>}
                                                data-testid="share-copy-link-button"
                                            >
                                                {copied ? t`Copied` : t`Copy link`}
                                            </Button>
                                        )}
                                    </CopyButton>
                                    <Button
                                        size="compact-sm"
                                        variant="default"
                                        onClick={downloadQrCode}
                                        leftSection={<IconDownload size={14}/>}
                                    >
                                        {t`Download QR code`}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </Modal>
    );
};
