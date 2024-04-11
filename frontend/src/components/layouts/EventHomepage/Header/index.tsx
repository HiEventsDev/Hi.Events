import classes from './Header.module.scss'
import { FC } from 'react';
import { Event } from '../../../../types.ts';

export const Header: FC<{
    event: Event
}> = ({event}) => {
   
    const coverImage = event?.images?.find((image) => image.type === 'EVENT_COVER');

    if (!coverImage) {
        return <></>;
    }

    return (
        <>
            <header className={classes.header}>
                <img
                    loading={'lazy'}
                    alt={event?.title}
                    src={coverImage.url}/>
            </header>
        </>
    )
}