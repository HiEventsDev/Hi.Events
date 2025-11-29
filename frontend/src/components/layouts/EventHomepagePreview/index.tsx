import {useEffect, useState} from "react";
import {useParams} from "react-router";
import {LoadingOverlay} from "@mantine/core";
import {Event, HomepageThemeSettings} from "../../../types.ts";
import {useGetEventPublic} from "../../../queries/useGetEventPublic.ts";
import {EventNotAvailable} from "../EventHomepage/EventNotAvailable";
import EventHomepage from "../EventHomepage";

interface PreviewSettings {
    homepage_theme_settings?: Partial<HomepageThemeSettings>;
    continue_button_text?: string;
}

const EventHomepagePreview = () => {
    const {eventId} = useParams();
    const {data: event, isFetched, isLoading} = useGetEventPublic(eventId);
    const [previewSettings, setPreviewSettings] = useState<PreviewSettings | null>(null);

    useEffect(() => {
        const handleMessage = (messageEvent: MessageEvent) => {
            if (messageEvent.data.type === "UPDATE_SETTINGS") {
                setPreviewSettings(messageEvent.data.settings);
            }
        };

        window.addEventListener("message", handleMessage);
        return () => window.removeEventListener("message", handleMessage);
    }, []);

    if (!isFetched || isLoading) {
        return <LoadingOverlay visible />;
    }

    if (!event) {
        return <EventNotAvailable />;
    }

    // Create a modified event with preview settings merged in
    let previewEvent: Event | undefined = event;

    if (previewSettings && event.settings) {
        previewEvent = {
            ...event,
            settings: {
                ...event.settings,
                homepage_theme_settings: previewSettings.homepage_theme_settings as HomepageThemeSettings || event.settings.homepage_theme_settings,
                continue_button_text: previewSettings.continue_button_text ?? event.settings.continue_button_text,
            }
        };
    }

    return (
        <EventHomepage
            event={previewEvent}
            promoCodeValid={undefined}
            promoCode={undefined}
        />
    );
};

export default EventHomepagePreview;
