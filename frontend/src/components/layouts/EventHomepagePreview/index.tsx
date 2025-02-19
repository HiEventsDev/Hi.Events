import EventHomepage from "../EventHomepage";
import {useEffect, useState} from "react";
import {useParams} from "react-router";
import {LoadingOverlay} from "@mantine/core";
import {EventSettings} from "../../../types.ts";
import {useGetEventPublic} from "../../../queries/useGetEventPublic.ts";

const EventHomepagePreview = () => {
    const {eventId} = useParams();
    const {data: event, isFetched, isLoading} = useGetEventPublic(eventId);
    const settings = event?.settings as EventSettings;
    const [updatedSettings, setUpdatedSettings] = useState<EventSettings | null>(settings);

    useEffect(() => {
        const handleMessage = (event: MessageEvent) => {
            if (event.data.type === "UPDATE_SETTINGS") {
                setUpdatedSettings(event.data.settings);
            }
        };

        window.addEventListener("message", handleMessage);
        return () => window.removeEventListener("message", handleMessage);
    }, []);

    if (!isFetched || isLoading) {
        return <LoadingOverlay/>;
    }

    return (
        <EventHomepage
            event={event}
            colors={{
                bodyBackground: updatedSettings?.homepage_body_background_color || settings.homepage_body_background_color,
                background: updatedSettings?.homepage_background_color || settings.homepage_background_color,
                primary: updatedSettings?.homepage_primary_color || settings.homepage_primary_color,
                primaryText: updatedSettings?.homepage_primary_text_color || settings.homepage_primary_text_color,
                secondary: updatedSettings?.homepage_secondary_color || settings.homepage_secondary_color,
                secondaryText: updatedSettings?.homepage_secondary_text_color || settings.homepage_secondary_text_color,
            }}
            backgroundType={updatedSettings?.homepage_background_type || settings.homepage_background_type}
            continueButtonText={updatedSettings?.continue_button_text || settings.continue_button_text}
        />
    );
};

export default EventHomepagePreview;
