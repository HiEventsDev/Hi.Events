import {ReactNode} from "react";
import {Helmet} from "react-helmet-async";
import {Icon} from "@tabler/icons-react";
import {Card} from "../../../../../components/common/Card";
import {PoweredByFooter} from "../../../../../components/common/PoweredByFooter";
import classes from "./Subscribe.module.scss";

export const StatusHeading = ({icon: StatusIcon, title, tone}: {icon: Icon, title: string, tone?: 'error'}) => (
    <>
        <Helmet>
            <title>{title}</title>
            <meta name="robots" content="noindex, nofollow"/>
        </Helmet>
        <StatusIcon size={40} className={classes.icon} data-tone={tone} aria-hidden/>
        <h1>{title}</h1>
    </>
);

export const StatusError = ({children}: {children: ReactNode}) => (
    <p className={classes.error} role="alert">{children}</p>
);

export const StatusPage = ({children}: {children: ReactNode}) => (
    <div className={classes.container}>
        <Card>
            <div className={classes.status}>{children}</div>
        </Card>
        <PoweredByFooter/>
    </div>
);
