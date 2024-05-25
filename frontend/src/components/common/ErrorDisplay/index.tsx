import {Container} from '@mantine/core';
import classes from './ErrorDisplay.module.scss';
import {useRouteError} from "react-router-dom";

export const ErrorDisplay = () => {
    const error = useRouteError();

    console.error(error);

    return (
        <Container className={classes.root}>
            <h2 className={classes.title}>Something went wrong</h2>
            <div className={classes.description}>
                Please refresh the page or try again later
            </div>
        </Container>
    );
};