import {ReactNode} from "react";
import classNames from "classnames";
import classes from "./StatusDot.module.scss";

interface StatusDotProps {
    tone: 'success' | 'warning' | 'muted';
    strong?: boolean;
    children: ReactNode;
}

export const StatusDot = ({tone, strong = false, children}: StatusDotProps) => (
    <span className={classNames(classes.status, classes[tone], {[classes.strong]: strong})}>
        {children}
    </span>
);
