import {CSSProperties, ReactNode} from "react";
import QRCode from "react-qr-code";
import {t} from "@lingui/macro";
import {Badge as BadgeData, BadgeLayout, BadgeLine} from "../../../types.ts";
import {BadgeImages, getBadgeDimensions, readableTextOn} from "../../../utilites/badges.ts";
import {buildHomepageFontStack} from "../../../constants/homepageFonts.ts";
import {formatDateWithLocale} from "../../../utilites/dates.ts";
import {FitText} from "./FitText.tsx";
import {FitBody} from "./FitBody.tsx";
import classes from "./NameBadge.module.scss";

export interface NameBadgeProps {
    layout: BadgeLayout;
    badge: BadgeData;
    eventName: string;
    images: BadgeImages;
    timezone?: string;
    side?: 'front' | 'back';
    showCutGuide?: boolean;
}

const NAME_SOURCES = ['FIRST_NAME', 'LAST_NAME', 'FULL_NAME'];

const TEMPLATE_DEFAULT_ALIGN: Record<BadgeLayout['template'], 'LEFT' | 'CENTER'> = {
    CLASSIC: 'LEFT',
    BANNER: 'CENTER',
    MINIMAL: 'LEFT',
    BOLD: 'LEFT',
    SIDEBAR: 'LEFT',
    ARTWORK: 'LEFT',
};

const lineText = (line: BadgeLine, timezone?: string): string => {
    if (line.source === 'OCCURRENCE_DATE' && line.text) {
        return formatDateWithLocale(line.text, 'weekdayDate', timezone ?? 'UTC');
    }
    return line.text;
};

const BadgeLines = ({badge, layout, timezone}: { badge: BadgeData; layout: BadgeLayout; timezone?: string }) => (
    <div className={classes.lines}>
        {badge.lines.map((line, index) => {
            const text = lineText(line, timezone);
            const isName = NAME_SOURCES.includes(line.source);
            return (
                <FitText
                    key={`${index}-${line.source}`}
                    className={classes.line}
                    dataSize={line.size}
                    fitKey={`${text}|${line.size}|${layout.size}|${layout.template}|${layout.font_family}|${layout.uppercase_names}|${layout.custom_width_mm}|${layout.custom_height_mm}`}
                    style={isName && layout.uppercase_names ? {textTransform: 'uppercase', letterSpacing: '0.02em'} : undefined}
                >
                    {text || ' '}
                </FitText>
            );
        })}
    </div>
);

const BadgeQr = ({value}: { value: string }) => (
    <div className={classes.qr}>
        <QRCode value={value} size={256} level="M" style={{width: '100%', height: '100%'}}/>
    </div>
);

const BadgeLogo = ({images, layout}: { images: BadgeImages; layout: BadgeLayout }) => (
    layout.show_logo && images.logoUrl
        ? <img className={classes.logo} src={images.logoUrl} alt="" draggable={false}/>
        : null
);

const BadgeEventName = ({eventName, layout}: { eventName: string; layout: BadgeLayout }) => (
    layout.show_event_name && eventName
        ? <div className={classes.eventName}>{eventName}</div>
        : null
);

const BadgeSeat = ({badge}: { badge: BadgeData }) => (
    badge.seat_label
        ? (
            <div className={classes.seat}>
                <span className={classes.seatLabel}>{t`Seat`}</span>
                <span>{badge.seat_label}</span>
            </div>
        )
        : null
);

const BadgeRibbon = ({badge, layout}: { badge: BadgeData; layout: BadgeLayout }) => (
    layout.show_ribbon && badge.ribbon?.label
        ? (
            <div className={classes.ribbon}>
                <FitText
                    className={classes.ribbonText}
                    maxLines={1}
                    fitKey={`${badge.ribbon.label}|${layout.size}|${layout.template}|${layout.font_family}|${layout.custom_width_mm}|${layout.custom_height_mm}`}
                >
                    {badge.ribbon.label}
                </FitText>
            </div>
        )
        : null
);

const BadgeSponsors = ({images, layout}: { images: BadgeImages; layout: BadgeLayout }) => (
    layout.show_sponsors && images.sponsorsUrl
        ? <img className={classes.sponsors} src={images.sponsorsUrl} alt="" draggable={false}/>
        : null
);

const BadgeFooter = ({layout}: { layout: BadgeLayout }) => (
    layout.footer_text ? <div className={classes.footer}>{layout.footer_text}</div> : null
);

const renderFront = (props: NameBadgeProps): ReactNode => {
    const {layout, badge, eventName, images, timezone} = props;
    const qr = layout.show_qr ? <BadgeQr value={badge.attendee_public_id}/> : null;
    const lines = <BadgeLines badge={badge} layout={layout} timezone={timezone}/>;

    switch (layout.template) {
        case 'BANNER':
            return (
                <>
                    <header className={classes.banner}>
                        {images.artworkUrl && <img className={classes.bannerArtwork} src={images.artworkUrl} alt="" draggable={false}/>}
                        <div className={classes.bannerContent}>
                            <BadgeLogo images={images} layout={layout}/>
                            <BadgeEventName eventName={eventName} layout={layout}/>
                        </div>
                    </header>
                    <FitBody className={classes.body} contentClassName={classes.bodyContent}>
                        {lines}
                        <BadgeSeat badge={badge}/>
                    </FitBody>
                    <div className={classes.bottom}>
                        <div className={classes.bottomStart}>
                            <BadgeSponsors images={images} layout={layout}/>
                            <BadgeFooter layout={layout}/>
                        </div>
                        {qr}
                    </div>
                    <BadgeRibbon badge={badge} layout={layout}/>
                </>
            );
        case 'MINIMAL':
            return (
                <>
                    <header className={classes.head}>
                        <BadgeLogo images={images} layout={layout}/>
                    </header>
                    <FitBody className={classes.body} contentClassName={classes.bodyContent}>
                        {lines}
                        <span className={classes.rule}/>
                        {layout.show_ribbon && badge.ribbon?.label && (
                            <div className={classes.minimalRibbon}>
                                <span className={classes.minimalRibbonMark}/>
                                {badge.ribbon.label}
                            </div>
                        )}
                        <BadgeSeat badge={badge}/>
                    </FitBody>
                    <div className={classes.bottom}>
                        <div className={classes.bottomStart}>
                            <BadgeEventName eventName={eventName} layout={layout}/>
                            <BadgeSponsors images={images} layout={layout}/>
                            <BadgeFooter layout={layout}/>
                        </div>
                        {qr}
                    </div>
                </>
            );
        case 'SIDEBAR':
            return (
                <>
                    <aside className={classes.sidebar}>
                        {layout.show_ribbon && badge.ribbon?.label && (
                            <span className={classes.sidebarText}>{badge.ribbon.label}</span>
                        )}
                    </aside>
                    <div className={classes.sidebarMain}>
                        <header className={classes.head}>
                            <BadgeLogo images={images} layout={layout}/>
                            <BadgeEventName eventName={eventName} layout={layout}/>
                        </header>
                        <FitBody className={classes.body} contentClassName={classes.bodyContent}>
                            {lines}
                            <BadgeSeat badge={badge}/>
                        </FitBody>
                        <div className={classes.bottom}>
                            <div className={classes.bottomStart}>
                                <BadgeSponsors images={images} layout={layout}/>
                                <BadgeFooter layout={layout}/>
                            </div>
                            {qr}
                        </div>
                    </div>
                </>
            );
        case 'ARTWORK':
            return (
                <>
                    {images.artworkUrl && <img className={classes.artwork} src={images.artworkUrl} alt="" draggable={false}/>}
                    <header className={classes.artworkHead}>
                        <BadgeLogo images={images} layout={layout}/>
                    </header>
                    <section className={classes.card}>
                        <BadgeEventName eventName={eventName} layout={layout}/>
                        <div className={classes.cardBody}>
                            {lines}
                            <BadgeSeat badge={badge}/>
                        </div>
                        <div className={classes.bottom}>
                            <div className={classes.bottomStart}>
                                <BadgeSponsors images={images} layout={layout}/>
                                <BadgeFooter layout={layout}/>
                            </div>
                            {qr}
                        </div>
                        <BadgeRibbon badge={badge} layout={layout}/>
                    </section>
                </>
            );
        case 'BOLD':
        case 'CLASSIC':
        default:
            return (
                <>
                    <header className={classes.head}>
                        <BadgeLogo images={images} layout={layout}/>
                        <BadgeEventName eventName={eventName} layout={layout}/>
                    </header>
                    <FitBody className={classes.body} contentClassName={classes.bodyContent}>
                        {lines}
                        <BadgeSeat badge={badge}/>
                    </FitBody>
                    <div className={classes.bottom}>
                        <div className={classes.bottomStart}>
                            <BadgeSponsors images={images} layout={layout}/>
                            <BadgeFooter layout={layout}/>
                        </div>
                        {qr}
                    </div>
                    <BadgeRibbon badge={badge} layout={layout}/>
                </>
            );
    }
};

const renderInfoBack = (props: NameBadgeProps): ReactNode => {
    const {layout, badge, eventName, images} = props;

    return (
        <div className={classes.back}>
            <header className={classes.backHead}>
                <BadgeLogo images={images} layout={layout}/>
                <BadgeEventName eventName={eventName} layout={{...layout, show_event_name: true}}/>
            </header>
            {layout.back_text && <div className={classes.backText}>{layout.back_text}</div>}
            <div className={classes.backBottom}>
                <BadgeSponsors images={images} layout={layout}/>
                {layout.show_qr && <BadgeQr value={badge.attendee_public_id}/>}
            </div>
        </div>
    );
};

export const NameBadge = (props: NameBadgeProps) => {
    const {layout, badge, side = 'front', showCutGuide} = props;
    const {width, height} = getBadgeDimensions(layout);
    const ribbonColor = badge.ribbon?.color ?? layout.accent_color;
    const isBold = layout.template === 'BOLD';
    const background = isBold ? layout.accent_color : (layout.background_color ?? '#ffffff');
    const ink = layout.text_color ?? readableTextOn(background);
    const align = layout.text_align ?? TEMPLATE_DEFAULT_ALIGN[layout.template];

    const style = {
        width: `${width}mm`,
        height: `${height}mm`,
        '--badge-font': buildHomepageFontStack(layout.font_family),
        '--badge-accent': layout.accent_color,
        '--badge-accent-ink': readableTextOn(layout.accent_color),
        '--badge-bg': background,
        '--badge-ink': ink,
        '--ribbon-bg': isBold ? '#ffffff' : ribbonColor,
        '--ribbon-ink': isBold ? layout.accent_color : readableTextOn(ribbonColor),
        '--badge-align': align === 'CENTER' ? 'center' : 'left',
        '--badge-justify': align === 'CENTER' ? 'center' : 'flex-start',
        '--badge-origin': align === 'CENTER' ? 'center' : 'left',
    } as CSSProperties;

    const isInfoBack = side === 'back' && layout.back_mode === 'INFO';

    return (
        <div
            className={classes.badge}
            data-template={isInfoBack ? 'INFO' : layout.template}
            data-orientation={width > height ? 'landscape' : 'portrait'}
            data-cut-guide={showCutGuide ? 'true' : undefined}
            data-testid="badge"
            style={style}
        >
            {isInfoBack ? renderInfoBack(props) : renderFront(props)}
        </div>
    );
};
