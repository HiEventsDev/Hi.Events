import {useState} from "react";
import {SegmentedControl} from "@mantine/core";
import {t} from "@lingui/macro";
import classNames from "classnames";
import {SharePreviewContent} from "../../../utilites/sharePreview.ts";
import classes from "./ShareModal.module.scss";

type PreviewStyle = 'feed' | 'message' | 'post';

interface SharePreviewProps {
    content: SharePreviewContent;
    caption: string;
    url: string;
    authorName: string;
}

const hostOf = (url: string) => {
    try {
        return new URL(url).host;
    } catch {
        return url;
    }
};

const LinkCard = ({content, url, compact = false}: { content: SharePreviewContent; url: string; compact?: boolean }) => (
    <div className={classNames(classes.linkCard, compact && classes.linkCardCompact)} data-testid="share-preview-card">
        {content.imageUrl && (
            <div className={classes.linkCardImage} style={{backgroundColor: content.imageBackground}}>
                <img src={content.imageUrl} alt="" data-testid="share-preview-image"/>
            </div>
        )}
        <div className={classes.linkCardBody}>
            <div className={classes.linkCardHost}>{hostOf(url)}</div>
            <div className={classes.linkCardTitle}>{content.title}</div>
            {content.description && <div className={classes.linkCardDescription}>{content.description}</div>}
        </div>
    </div>
);

export const SharePreview = ({content, caption, url, authorName}: SharePreviewProps) => {
    const [style, setStyle] = useState<PreviewStyle>('feed');
    const initial = authorName.trim().charAt(0).toUpperCase();

    return (
        <div className={classes.preview}>
            <SegmentedControl
                size="xs"
                value={style}
                onChange={(value) => setStyle(value as PreviewStyle)}
                aria-label={t`Preview style`}
                data={[
                    {value: 'feed', label: t`Feed`},
                    {value: 'message', label: t`Message`},
                    {value: 'post', label: t`Post`},
                ]}
            />

            <div className={classes.previewStage}>
                {style === 'feed' && (
                    <div className={classes.feedPost}>
                        <div className={classes.author}>
                            <span className={classes.avatar} aria-hidden>{initial}</span>
                            <span>
                                <span className={classes.authorName}>{authorName}</span>
                                <span className={classes.authorMeta}>{t`Just now`}</span>
                            </span>
                        </div>
                        {caption && <p className={classes.postText}>{caption}</p>}
                        <LinkCard content={content} url={url}/>
                    </div>
                )}

                {style === 'message' && (
                    <div className={classes.messageThread}>
                        <div className={classes.messageBubble}>
                            <LinkCard content={content} url={url} compact/>
                            <p className={classes.messageText}>
                                {caption && <>{caption} </>}
                                <span className={classes.messageLink}>{url}</span>
                            </p>
                        </div>
                    </div>
                )}

                {style === 'post' && (
                    <div className={classes.feedPost}>
                        <div className={classes.author}>
                            <span className={classes.avatar} aria-hidden>{initial}</span>
                            <span className={classes.authorName}>{authorName}</span>
                        </div>
                        <p className={classes.postText}>
                            {caption && <>{caption} </>}
                            <span className={classes.messageLink}>{hostOf(url)}</span>
                        </p>
                        <div className={classes.postCard}>
                            {content.imageUrl ? (
                                <div className={classes.linkCardImage} style={{backgroundColor: content.imageBackground}}>
                                    <img src={content.imageUrl} alt=""/>
                                    <span className={classes.postCardLabel}>{content.title}</span>
                                </div>
                            ) : (
                                <div className={classes.linkCardBody}>
                                    <div className={classes.linkCardHost}>{hostOf(url)}</div>
                                    <div className={classes.linkCardTitle}>{content.title}</div>
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};
