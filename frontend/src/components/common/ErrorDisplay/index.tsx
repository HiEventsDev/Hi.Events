import {Container} from '@mantine/core';
import classes from './ErrorDisplay.module.scss';
import {useRouteError} from "react-router-dom";
import {t} from "@lingui/macro";

export const ErrorDisplay = () => {
    const error = useRouteError() as any

    const title = error?.status === 404
        ? t`Page not found`
        : t`Something went wrong`;

    const description = error?.status === 404
        ? t`The page you are looking for does not exist`
        : t`An error occurred while loading the page`;

    return (
        <Container className={classes.root}>
            <div className={classes.logo}>
                <img src="/logo-dark.svg" alt="Error"/>
            </div>
            <h2 className={classes.title}>{title}</h2>
            <div className={classes.description}>
                {description}
            </div>

            <div className={classes.actions}>
                Go back to the <a href="/" className={classes.link}>home page</a>
            </div>
        </Container>
    );
};