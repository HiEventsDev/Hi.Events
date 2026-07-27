import {CSSProperties, ReactNode} from "react";
import {t} from "@lingui/macro";
import {
    IconAlertTriangle,
    IconBulb,
    IconCheck,
    IconInfoCircle,
    IconX,
} from "@tabler/icons-react";
import classes from "./Callout.module.scss";

export type CalloutVariant = "info" | "tip" | "warning" | "success";

interface CalloutProps {
    variant?: CalloutVariant;
    title?: ReactNode;
    children?: ReactNode;
    icon?: ReactNode;
    onDismiss?: () => void;
    className?: string;
    style?: CSSProperties;
}

const defaultIcon: Record<CalloutVariant, ReactNode> = {
    info: <IconInfoCircle size={18} stroke={2}/>,
    tip: <IconBulb size={18} stroke={2}/>,
    warning: <IconAlertTriangle size={18} stroke={2}/>,
    success: <IconCheck size={18} stroke={2.4}/>,
};

export const Callout = ({
                            variant = "info",
                            title,
                            children,
                            icon,
                            onDismiss,
                            className,
                            style,
                        }: CalloutProps) => {
    return (
        <aside className={`${classes.callout} ${classes[variant]} ${className ?? ""}`} style={style}>
            <div className={`${classes.iconWrap} ${classes[`iconWrap_${variant}`]}`} aria-hidden="true">
                {icon ?? defaultIcon[variant]}
            </div>
            <div className={classes.body}>
                {title && <div className={classes.title}>{title}</div>}
                {children && <div className={classes.text}>{children}</div>}
            </div>
            {onDismiss && (
                <button
                    type="button"
                    onClick={onDismiss}
                    className={classes.dismissBtn}
                    aria-label={t`Dismiss`}
                >
                    <IconX size={14}/>
                </button>
            )}
        </aside>
    );
};
