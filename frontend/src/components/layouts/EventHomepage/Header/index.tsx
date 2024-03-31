import classes from './Header.module.scss'
import {useGetEventPublic} from "../../../../queries/useGetEventPublic.ts";
import {useParams} from "react-router-dom";

export const Header = () => {
    const {eventId} = useParams();
    const {data: event} = useGetEventPublic(eventId);

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