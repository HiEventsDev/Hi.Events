import React from "react";
import classes from './NoResultsSplash.module.scss';
import {useSearchParams} from "react-router-dom";

interface NoResultsSplashProps {
    heading?: React.ReactNode,
    children?: React.ReactNode,
    subHeading?: React.ReactNode,
}

export const NoResultsSplash = ({heading = 'There\'s nothing to show yet', children, subHeading}: NoResultsSplashProps) => {
    const [searchParams] = useSearchParams();
    const hasSearchQuery = !!searchParams.get('query');

    return (
        <div className={classes.container}>
            <img alt={'No results'} width={300} src={'/no-results-empty-boxes.svg'}/>

            {heading && !hasSearchQuery && <h2>{heading}</h2>}

            {hasSearchQuery && (
                <h2>No search results.</h2>
            )}

            {(subHeading && !hasSearchQuery) && subHeading}

            {children && children}
        </div>
    )
}