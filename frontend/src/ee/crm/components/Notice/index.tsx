import {CSSProperties, ReactNode} from "react";
import {IconAlertCircle, IconAlertTriangle, IconInfoCircle} from "@tabler/icons-react";
import classes from "./Notice.module.scss";

export type NoticeTone = 'warning' | 'info' | 'danger';

interface NoticeProps {
    tone?: NoticeTone;
    title?: ReactNode;
    icon?: ReactNode;
    children?: ReactNode;
    className?: string;
    style?: CSSProperties;
    'data-testid'?: string;
}

const ICONS: Record<NoticeTone, ReactNode> = {
    warning: <IconAlertTriangle size={18} stroke={1.8}/>,
    info: <IconInfoCircle size={18} stroke={1.8}/>,
    danger: <IconAlertCircle size={18} stroke={1.8}/>,
};

export const Notice = ({tone = 'warning', title, icon, children, className, style, 'data-testid': testId}: NoticeProps) => {
    const Element = tone === 'danger' ? 'div' : 'aside';

    return (
        <Element
            className={`${classes.notice} ${className ?? ''}`}
            style={style}
            data-tone={tone}
            data-testid={testId}
            role={tone === 'danger' ? 'alert' : undefined}
        >
            <span className={classes.icon} aria-hidden="true">{icon ?? ICONS[tone]}</span>
            <div className={classes.body}>
                {title && <div className={classes.title}>{title}</div>}
                {children && <div className={classes.text}>{children}</div>}
            </div>
        </Element>
    );
};
