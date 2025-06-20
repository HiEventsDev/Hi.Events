import {useEffect, useState} from "react";
import {useLoaderData, useParams} from "react-router";
import {useGetOrganizerSettings} from "../../../queries/useGetOrganizerSettings.ts";
import {LoadingMask} from "../../common/LoadingMask";
import OrganizerHomepage from "../OrganizerHomepage";
import {Organizer} from "../../../types.ts";

const OrganizerHomepagePreview = () => {
    const {organizerId} = useParams();

    const {organizer, eventsData, isPastEvents} = useLoaderData() as {
        organizer: Organizer | null;
        eventsData: any;
        isPastEvents: boolean;
    };

    const organizerSettingsQuery = useGetOrganizerSettings(organizerId);
    const [previewSettings, setPreviewSettings] = useState<any>(null);

    useEffect(() => {
        const handleMessage = (event: MessageEvent) => {
            if (event.data?.type === "UPDATE_ORGANIZER_SETTINGS") {
                setPreviewSettings(event.data.settings);
            }
        };

        window.addEventListener("message", handleMessage);
        return () => window.removeEventListener("message", handleMessage);
    }, []);

    if (!organizer || organizerSettingsQuery.isLoading) {
        return <LoadingMask/>;
    }

    if (!organizer || !organizerSettingsQuery.data) {
        return null;
    }

    // Merge preview settings with actual data
    const previewOrganizer = {
        ...organizer,
        images: organizer.images?.map(img => {
            if (img.type === 'ORGANIZER_LOGO' && previewSettings?.logoUrl) {
                return {...img, url: previewSettings.logoUrl};
            }
            if (img.type === 'ORGANIZER_COVER' && previewSettings?.coverUrl) {
                return {...img, url: previewSettings.coverUrl};
            }
            return img;
        }),
        settings: {
            ...organizerSettingsQuery.data,
            homepage_theme_settings: {
                ...organizerSettingsQuery.data.homepage_theme_settings,
                ...previewSettings
            }
        }
    };

    return <OrganizerHomepage
        organizer={previewOrganizer}
        eventsData={eventsData}
        isPastEvents={false}
    />;
};

export default OrganizerHomepagePreview;
