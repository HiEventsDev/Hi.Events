import { useParams, useLocation } from "react-router-dom";
import { LoadingMask } from "../../common/LoadingMask";
import '../../../styles/widget/default.scss';
import { useGetEventPublic } from "../../../queries/useGetEventPublic.ts";
import SelectTickets from "../../routes/ticket-widget/SelectTickets";
import { useMemo } from "react";

const TicketWidget = () => {
    const { eventId } = useParams();
    const location = useLocation();
    const eventQuery = useGetEventPublic(eventId);

    const settings = useMemo(() => {
        const searchParams = new URLSearchParams(location.search);

        return {
            colors: {
                background: searchParams.get("BackgroundColor") || '#ffffff',
                primary: searchParams.get("PrimaryColor") || '#7b5db8',
                primaryText: searchParams.get("PrimaryTextColor") || '#000000',
                secondary: searchParams.get("SecondaryColor") || '#7b5eb9',
                secondaryText: searchParams.get("SecondaryTextColor") || '#ffffff',
            },
            continueButtonText: searchParams.get("ContinueButtonText") || 'Continue',
            padding: searchParams.get("Padding") || '10px',
        };
    }, [location.search]);

    if (!eventQuery.isFetched || !eventQuery.data) {
        return <LoadingMask />;
    }

    return (
        <div className={'hi-ticket-widget-container full-height'}>
            <SelectTickets
                widgetMode={'embedded'}
                event={eventQuery.data}
                colors={settings.colors}
                continueButtonText={settings.continueButtonText}
                padding={settings.padding}
            />
        </div>
    );
};

export default TicketWidget;
