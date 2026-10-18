import {ReactNode} from "react";
import {IconCheck} from "@tabler/icons-react";
import classes from "./SingleSignOn.module.scss";

interface SsoStepProps {
    number: number;
    title: string;
    description?: ReactNode;
    done?: boolean;
    locked?: boolean;
    children?: ReactNode;
}

export const SsoStep = ({number, title, description, done = false, locked = false, children}: SsoStepProps) => (
    <section className={classes.step} data-locked={locked || undefined} aria-disabled={locked || undefined}>
        <div className={classes.stepMarker} data-done={done || undefined} aria-hidden="true">
            {done ? <IconCheck size={14} stroke={2.6}/> : number}
        </div>
        <div className={classes.stepBody}>
            <h3 className={classes.stepTitle}>{title}</h3>
            {description && <div className={classes.stepDescription}>{description}</div>}
            {!locked && children && <div className={classes.stepContent}>{children}</div>}
        </div>
    </section>
);
