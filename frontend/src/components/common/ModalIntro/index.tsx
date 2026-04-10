import {ReactNode} from "react";
import classes from './ModalIntro.module.scss';

interface ModalIntroProps {
    icon: ReactNode;
    title: string;
    subtitle: string;
}

export const ModalIntro = ({icon, title, subtitle}: ModalIntroProps) => (
    <div className={classes.banner}>
        <div className={classes.icon}>{icon}</div>
        <div className={classes.title}>{title}</div>
        <div className={classes.subtext}>{subtitle}</div>
    </div>
);
