import React from "react";
import classes from './NoResultsSplash.module.scss';
import {useSearchParams} from "react-router-dom";
import {t} from "@lingui/macro";

interface NoResultsSplashProps {
    heading?: React.ReactNode,
    children?: React.ReactNode,
    subHeading?: React.ReactNode,
    imageHref?: string
}

export const NoResultsSplash = ({
                                    heading = t`'There\'s nothing to show yet'`,
                                    children,
                                    subHeading,
                                    imageHref = '/no-results-empty-boxes.svg',
                                }: NoResultsSplashProps) => {
    const [searchParams] = useSearchParams();
    const hasSearchQuery = !!searchParams.get('query');

    return (
        <div className={classes.container}>
            <img alt={t`No results`} width={300} src={imageHref}/>

            {heading && !hasSearchQuery && <h2>{heading}</h2>}

            {hasSearchQuery && (
                <h2>{t`No search results.`}</h2>
            )}

            {(subHeading && !hasSearchQuery) && subHeading}

            {children && children}
        </div>
    )
}
