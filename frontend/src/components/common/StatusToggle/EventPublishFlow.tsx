import {useState} from 'react';
import {Event} from '../../../types';
import {PublishEventModal} from '../../modals/PublishEventModal';
import {EventLiveCelebrationModal} from '../../modals/EventLiveCelebrationModal';
import {eventHomepageUrl} from '../../../utilites/urlHelper';
import {trackEvent, AnalyticsEvents} from '../../../utilites/analytics';

export interface EventPublishFlowProps {
    event: Event;
    onPublished: () => void;
    onClose: () => void;
}

export const EventPublishFlow = ({event, onPublished, onClose}: EventPublishFlowProps) => {
    const [isPublished, setIsPublished] = useState(false);

    const handlePublished = () => {
        trackEvent(AnalyticsEvents.EVENT_PUBLISHED);
        setIsPublished(true);
        onPublished();
    };

    if (isPublished) {
        return (
            <EventLiveCelebrationModal
                opened
                onClose={onClose}
                url={eventHomepageUrl(event)}
                eventTitle={event.title}
                eventId={String(event.id)}
            />
        );
    }

    return (
        <PublishEventModal
            opened
            onClose={onClose}
            event={event}
            onSuccess={handlePublished}
        />
    );
};
