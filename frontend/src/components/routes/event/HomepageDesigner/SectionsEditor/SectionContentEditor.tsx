import {useState} from "react";
import {t} from "@lingui/macro";
import {ActionIcon, Button, Loader, Textarea, TextInput} from "@mantine/core";
import {Dropzone, IMAGE_MIME_TYPE} from "@mantine/dropzone";
import {IconArrowDown, IconArrowUp, IconPhotoPlus, IconPlus, IconX} from "@tabler/icons-react";
import {NavLink} from "react-router";
import {HomepageSection, HomepageSectionFaq, HomepageSectionSponsor, IdParam, Image} from "../../../../../types.ts";
import {Editor} from "../../../../common/Editor";
import {useImageUploads} from "../../../../../hooks/useImageUploads.ts";
import {showError} from "../../../../../utilites/notifications.tsx";
import {videoEmbedUrl} from "../../../../layouts/EventHomepage/Sections/VideoSection.tsx";
import classes from "./SectionsEditor.module.scss";

const MAX_FILE_SIZE = 5 * 1024 * 1024;
const MAX_GALLERY_PHOTOS = 40;
const MAX_SPONSORS = 60;
const MAX_FAQ_ANSWER_LENGTH = 5000;
const MAX_TEXT_LENGTH = 50000;

export type SectionsEditorVariant = 'event' | 'organizer';

export type SectionChanges = Partial<HomepageSection> | ((section: HomepageSection) => Partial<HomepageSection>);

interface SectionContentEditorProps {
    variant: SectionsEditorVariant;
    entityId: IdParam;
    section: HomepageSection;
    images: Image[];
    ticketsBesideContent: boolean;
    disabled?: boolean;
    isEmpty?: boolean;
    onChange: (changes: SectionChanges) => void;
    onImagesChanged: () => void;
}

const BUILT_IN_WITH_NOTICE = ['ABOUT', 'LINEUP', 'SCHEDULE', 'LOCATION', 'ORGANIZER'];

const isFaqAnswerTooLong = (answer?: string | null): boolean => (answer ?? '').length > MAX_FAQ_ANSWER_LENGTH;

export const sectionExceedsLimits = (section: HomepageSection): boolean =>
    (section.type === 'TEXT' && (section.body ?? '').length > MAX_TEXT_LENGTH)
    || (section.type === 'FAQ' && (section.faqs ?? []).some(faq => isFaqAnswerTooLong(faq.answer)));

export const SectionContentEditor = (props: SectionContentEditorProps) => {
    const {variant, entityId, section, ticketsBesideContent, disabled, isEmpty, onChange} = props;

    if (isEmpty && BUILT_IN_WITH_NOTICE.includes(section.type)) {
        return null;
    }

    switch (section.type) {
        case 'TEXT':
            return (
                <Editor
                    label={t`Text`}
                    editorType="simple"
                    value={section.body ?? ''}
                    onChange={value => onChange({body: value})}
                    maxLength={MAX_TEXT_LENGTH}
                />
            );
        case 'FAQ':
            return <FaqEditor faqs={section.faqs ?? []} disabled={disabled} onChange={faqs => onChange({faqs})}/>;
        case 'VIDEO':
            return <VideoEditor url={section.video_url ?? ''} disabled={disabled} onChange={video_url => onChange({video_url})}/>;
        case 'GALLERY':
            return <GalleryEditor {...props}/>;
        case 'SPONSORS':
            return <SponsorsEditor {...props}/>;
        case 'ABOUT':
            return variant === 'event'
                ? <p className={classes.help}>{t`Shows your event description.`} <NavLink to={`/manage/event/${entityId}/settings`}>{t`Edit it in Event Settings`}</NavLink></p>
                : (
                    <p className={classes.help}>
                        {t`Shows your organizer description. Placed above every other section, it appears in your profile card.`}{' '}
                        <NavLink to={`/manage/organizer/${entityId}/settings`}>{t`Edit it in Settings`}</NavLink>
                    </p>
                );
        case 'CONTACT':
            return <p className={classes.help}>{t`Shows your social links and a contact button. Placed above every other section, it appears in your profile card.`}</p>;
        case 'EVENTS':
            return <p className={classes.help}>{t`Always shown. Move it to change where your events list appears.`}</p>;
        case 'PAST_EVENTS':
            return <p className={classes.help}>{t`Directly below Events, it's a Past tab on the events list. Anywhere else, it shows your most recent past events.`}</p>;
        case 'LINEUP':
        case 'SCHEDULE':
            return <p className={classes.help}>{t`Manage people, stages and times on the`} <NavLink to={`/manage/event/${entityId}/lineup`}>{t`Lineup & Schedule page`}</NavLink>. {t`Hidden automatically when empty.`}</p>;
        case 'LOCATION':
            return <p className={classes.help}>{t`Shows the venue and a map. Hidden automatically for online events.`}</p>;
        case 'ORGANIZER':
            return <p className={classes.help}>{t`Shows your organizer profile with a contact button.`}</p>;
        case 'MORE_EVENTS':
            return <p className={classes.help}>{t`Shows up to three of your other upcoming events. Hidden automatically when there are none.`}</p>;
        case 'TICKETS':
            return ticketsBesideContent
                ? <p className={classes.help}>{t`Always shown. On large screens tickets sit beside your page content; move it to change where they appear on phones and tablets.`}</p>
                : <p className={classes.help}>{t`Always shown. Move it to change where your tickets appear.`}</p>;
        default:
            return null;
    }
};

const paragraphsToText = (html?: string | null): string => (html ?? '')
    .replace(/<\/p>\s*<p>/g, '\n\n')
    .replace(/<br\s*\/?>/g, '\n')
    .replace(/<[^>]+>/g, '')
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#39;/g, "'");

const textToParagraphs = (text: string): string => text
    .split(/\n{2,}/)
    .map(paragraph => paragraph.trim())
    .filter(Boolean)
    .map(paragraph => `<p>${paragraph
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\n/g, '<br>')}</p>`)
    .join('');

const FaqEditor = ({faqs, disabled, onChange}: { faqs: HomepageSectionFaq[], disabled?: boolean, onChange: (faqs: HomepageSectionFaq[]) => void }) => {
    const move = (index: number, delta: number) => {
        const next = [...faqs];
        const [item] = next.splice(index, 1);
        next.splice(index + delta, 0, item);
        onChange(next);
    };

    return (
        <div className={classes.stack}>
            {faqs.map((faq, index) => {
                const number = index + 1;
                return (
                    <div key={index} className={classes.card}>
                        <div className={classes.cardHeader}>
                            <span className={classes.cardTitle}>{t`Question ${number}`}</span>
                            <ActionIcon size="sm" variant="subtle" color="gray" disabled={disabled || index === 0} onClick={() => move(index, -1)} aria-label={t`Move up`}>
                                <IconArrowUp size={14}/>
                            </ActionIcon>
                            <ActionIcon size="sm" variant="subtle" color="gray" disabled={disabled || index === faqs.length - 1} onClick={() => move(index, 1)} aria-label={t`Move down`}>
                                <IconArrowDown size={14}/>
                            </ActionIcon>
                            <ActionIcon size="sm" variant="subtle" color="red" disabled={disabled} onClick={() => onChange(faqs.filter((_, i) => i !== index))} aria-label={t`Remove question`}>
                                <IconX size={14}/>
                            </ActionIcon>
                        </div>
                        <TextInput
                            size="xs"
                            placeholder={t`e.g. Is there parking?`}
                            value={faq.question}
                            maxLength={300}
                            disabled={disabled}
                            aria-label={t`Question ${number}`}
                            onChange={event => onChange(faqs.map((item, i) => i === index ? {...item, question: event.currentTarget.value} : item))}
                        />
                        <FaqAnswerInput
                            answer={faq.answer}
                            number={number}
                            disabled={disabled}
                            onChange={answer => onChange(faqs.map((item, i) => i === index ? {...item, answer} : item))}
                        />
                    </div>
                );
            })}
            <Button
                variant="subtle"
                size="xs"
                leftSection={<IconPlus size={14}/>}
                className={classes.inlineAdd}
                disabled={disabled || faqs.length >= 50}
                onClick={() => onChange([...faqs, {question: '', answer: ''}])}
                data-testid="homepage-faq-add"
            >
                {t`Add question`}
            </Button>
        </div>
    );
};

const FaqAnswerInput = ({answer, number, disabled, onChange}: { answer?: string | null, number: number, disabled?: boolean, onChange: (answer: string) => void }) => {
    const [text, setText] = useState(() => paragraphsToText(answer));
    const [syncedAnswer, setSyncedAnswer] = useState(answer);

    if (answer !== syncedAnswer) {
        setSyncedAnswer(answer);
        if ((answer ?? '') !== textToParagraphs(text)) {
            setText(paragraphsToText(answer));
        }
    }

    return (
        <Textarea
            size="xs"
            autosize
            minRows={2}
            placeholder={t`Answer`}
            value={text}
            disabled={disabled}
            aria-label={t`Answer ${number}`}
            error={isFaqAnswerTooLong(textToParagraphs(text)) ? t`This answer is too long. Shorten it before saving.` : undefined}
            onChange={event => {
                const value = event.currentTarget.value;
                setText(value);
                onChange(textToParagraphs(value));
            }}
        />
    );
};

const VideoEditor = ({url, disabled, onChange}: { url: string, disabled?: boolean, onChange: (url: string | null) => void }) => {
    const embed = videoEmbedUrl(url);
    const provider = embed?.provider;

    return (
        <TextInput
            size="xs"
            label={t`Video link`}
            placeholder="https://www.youtube.com/watch?v=…"
            value={url}
            disabled={disabled}
            description={url && (provider ? t`${provider} video found` : t`Paste a YouTube or Vimeo link`)}
            error={url && !embed ? t`That doesn't look like a YouTube or Vimeo video link` : undefined}
            onChange={event => onChange(event.currentTarget.value.replace(/^\s*http:\/\//i, 'https://') || null)}
            data-testid="homepage-video-url"
        />
    );
};

const GalleryEditor = ({variant, entityId, section, images, disabled, onChange, onImagesChanged}: SectionContentEditorProps) => {
    const ids = section.image_ids ?? [];
    const {uploading, upload} = useImageUploads({
        entityId,
        type: variant === 'event' ? 'EVENT_GALLERY' : 'ORGANIZER_GALLERY',
        room: MAX_GALLERY_PHOTOS - ids.length,
        onLimitReached: () => showError(t`A gallery can have up to ${MAX_GALLERY_PHOTOS} photos.`),
        onUploaded: newIds => {
            onChange(current => ({image_ids: [...(current.image_ids ?? []), ...newIds]}));
            onImagesChanged();
        },
    });
    const galleryImages = ids
        .map(id => images.find(image => Number(image.id) === id))
        .filter((image): image is Image => !!image);

    const move = (index: number, delta: number) => {
        const next = [...ids];
        const [item] = next.splice(index, 1);
        next.splice(index + delta, 0, item);
        onChange({image_ids: next});
    };

    return (
        <div className={classes.stack}>
            <div className={classes.thumbGrid}>
                {galleryImages.map((image, index) => (
                    <div key={image.id} className={classes.thumb}>
                        <img src={image.url} alt=""/>
                        <div className={classes.thumbActions}>
                            <ActionIcon size={24} variant="filled" color="dark" disabled={disabled || index === 0} onClick={() => move(index, -1)} aria-label={t`Move left`}>
                                <IconArrowUp size={14} style={{transform: 'rotate(-90deg)'}}/>
                            </ActionIcon>
                            <ActionIcon size={24} variant="filled" color="dark" disabled={disabled} onClick={() => onChange({image_ids: ids.filter(id => id !== Number(image.id))})} aria-label={t`Remove photo`}>
                                <IconX size={14}/>
                            </ActionIcon>
                        </div>
                    </div>
                ))}
                {Array.from({length: uploading}).map((_, index) => (
                    <div key={`uploading-${index}`} className={classes.thumb} data-pending><Loader size="xs"/></div>
                ))}
                {!disabled && ids.length + uploading < MAX_GALLERY_PHOTOS && (
                    <Dropzone
                        className={classes.thumbDropzone}
                        onDrop={upload}
                        onReject={() => showError(t`Use a JPG, PNG or WebP image under 5MB.`)}
                        accept={IMAGE_MIME_TYPE}
                        maxSize={MAX_FILE_SIZE}
                        multiple
                        data-testid="homepage-gallery-dropzone"
                    >
                        <IconPhotoPlus size={18} stroke={1.5}/>
                    </Dropzone>
                )}
            </div>
            <p className={classes.help}>{t`Up to 40 photos, at least 400 × 300px. The first one is shown largest.`}</p>
        </div>
    );
};

const SponsorsEditor = ({variant, entityId, section, images, disabled, onChange, onImagesChanged}: SectionContentEditorProps) => {
    const sponsors = section.sponsors ?? [];
    const {uploading, upload} = useImageUploads({
        entityId,
        type: variant === 'event' ? 'EVENT_SPONSOR_LOGO' : 'ORGANIZER_SPONSOR_LOGO',
        room: MAX_SPONSORS - sponsors.length,
        onLimitReached: () => showError(t`You can add up to ${MAX_SPONSORS} sponsors.`),
        onUploaded: newIds => {
            onChange(current => ({sponsors: [...(current.sponsors ?? []), ...newIds.map(image_id => ({image_id, name: '', url: ''}))]}));
            onImagesChanged();
        },
    });

    const updateSponsor = (imageId: number, changes: Partial<HomepageSectionSponsor>) =>
        onChange(current => ({sponsors: (current.sponsors ?? []).map(sponsor => sponsor.image_id === imageId ? {...sponsor, ...changes} : sponsor)}));

    return (
        <div className={classes.stack}>
            {sponsors.map(sponsor => {
                const logo = images.find(image => Number(image.id) === sponsor.image_id);
                return (
                    <div key={sponsor.image_id} className={classes.sponsorRow}>
                        <div className={classes.sponsorLogo}>{logo && <img src={logo.url} alt=""/>}</div>
                        <div className={classes.stack}>
                            <TextInput size="xs" placeholder={t`Sponsor name`} value={sponsor.name ?? ''} maxLength={100} disabled={disabled}
                                       onChange={event => updateSponsor(sponsor.image_id, {name: event.currentTarget.value})}/>
                            <SponsorUrlInput url={sponsor.url ?? ''} disabled={disabled} onChange={url => updateSponsor(sponsor.image_id, {url})}/>
                        </div>
                        <ActionIcon size="sm" variant="subtle" color="red" disabled={disabled}
                                    onClick={() => onChange(current => ({sponsors: (current.sponsors ?? []).filter(candidate => candidate.image_id !== sponsor.image_id)}))}
                                    aria-label={t`Remove sponsor`}>
                            <IconX size={14}/>
                        </ActionIcon>
                    </div>
                );
            })}
            {uploading > 0 && <Loader size="xs"/>}
            {sponsors.length >= MAX_SPONSORS && (
                <p className={classes.help} data-testid="homepage-sponsors-limit">{t`You've added the maximum of ${MAX_SPONSORS} sponsors.`}</p>
            )}
            {!disabled && sponsors.length + uploading < MAX_SPONSORS && (
                <Dropzone
                    className={classes.logoDropzone}
                    onDrop={upload}
                    onReject={() => showError(t`Use a JPG, PNG or WebP image under 5MB.`)}
                    accept={IMAGE_MIME_TYPE}
                    maxSize={MAX_FILE_SIZE}
                    multiple
                >
                    <IconPhotoPlus size={16} stroke={1.5}/>
                    <span>{t`Upload sponsor logos`}</span>
                </Dropzone>
            )}
        </div>
    );
};

const normaliseSponsorUrl = (value: string): string => {
    const trimmed = value.trim();
    if (!trimmed) {
        return '';
    }
    const withoutScheme = trimmed.replace(/^https?:\/\//i, '').replace(/^\/+/, '');
    return /^[a-z][a-z\d+.-]*:\/\//i.test(withoutScheme) ? trimmed : `https://${withoutScheme}`;
};

const isHttpsUrl = (value: string): boolean => {
    try {
        const url = new URL(value);
        return url.protocol === 'https:' && url.hostname.includes('.');
    } catch {
        return false;
    }
};

const SponsorUrlInput = ({url, disabled, onChange}: { url: string, disabled?: boolean, onChange: (url: string) => void }) => {
    const [checked, setChecked] = useState(false);

    return (
        <TextInput
            size="xs"
            placeholder="https://"
            value={url}
            maxLength={500}
            disabled={disabled}
            error={checked && url && !isHttpsUrl(url) ? t`Enter a full link starting with https://` : undefined}
            onChange={event => {
                setChecked(false);
                onChange(event.currentTarget.value);
            }}
            onBlur={() => {
                setChecked(true);
                const normalised = normaliseSponsorUrl(url);
                if (normalised !== url) {
                    onChange(normalised);
                }
            }}
        />
    );
};
