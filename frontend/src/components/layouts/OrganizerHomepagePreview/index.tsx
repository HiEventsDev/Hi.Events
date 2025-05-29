import {useEffect, useState} from "react";
import {useParams} from "react-router";
import {useGetOrganizerSettings} from "../../../queries/useGetOrganizerSettings.ts";
import {LoadingMask} from "../../common/LoadingMask";
import PublicOrganizer from "../PublicOrganizer";
import {useGetOrganizerPublic} from "../../../queries/useGetOrganizerPublic.ts";

const OrganizerHomepagePreview = () => {
    const {organizerId} = useParams();
    const organizerQuery = useGetOrganizerPublic(organizerId);
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

    if (organizerQuery.isLoading || organizerSettingsQuery.isLoading) {
        return <LoadingMask/>;
    }

    if (!organizerQuery.data || !organizerSettingsQuery.data) {
        return null;
    }

    // Merge preview settings with actual data
    const previewOrganizer = {
        ...organizerQuery.data,
        images: organizerQuery.data.images?.map(img => {
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

    return <PublicOrganizer previewData={previewOrganizer} isPreview={true}/>;
};

export default OrganizerHomepagePreview;
