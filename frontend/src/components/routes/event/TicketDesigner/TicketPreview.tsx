import {useGetEvent} from "../../../../queries/useGetEvent.ts";
import {useGetMe} from "../../../../queries/useGetMe.ts";
import {t} from "@lingui/macro";
import {Attendee, IdParam, LocationType, Product, ProductPriceType, ProductType} from "../../../../types.ts";
import {useGetEventSettings} from "../../../../queries/useGetEventSettings.ts";
import {AttendeeTicket} from "../../../common/AttendeeTicket";
import {resolveEventLocation} from "../../../../utilites/effectiveLocation.ts";
import classes from './TicketPreview.module.scss';

export interface TicketDesignSettings {
    accent_color: string;
    footer_text: string;
    date_display_mode: 'START_DATE_TIME' | 'DATE_RANGE' | 'HIDDEN';
    enabled: boolean;
}

interface TicketPreviewProps {
    settings: TicketDesignSettings;
    eventId: IdParam;
    logo?: {id: IdParam; url: string};
}

export const TicketPreview = ({settings, eventId, logo}: TicketPreviewProps) => {
    const eventQuery = useGetEvent(eventId);
    const meQuery = useGetMe();
    const eventSettingsQuery = useGetEventSettings(eventId);

    const event = eventQuery.data;
    const user = meQuery.data;
    const eventSettings = eventSettingsQuery.data;

    if (!event || !user || !eventSettings) {
        return (
            <div className={classes.loadingState}>
                <p>{t`Loading preview...`}</p>
            </div>
        );
    }

    const mockProduct: Product = {
        id: 1,
        title: t`General Admission`,
        price: 2500,
        type: ProductPriceType.Paid,
        product_type: ProductType.Ticket,
        is_hidden: false,
        description: "",
        is_hidden_without_promo_code: false
    };

    const mockAttendee: Attendee = {
        id: 1,
        public_id: "PREVIEW12345",
        short_id: "P1234",
        first_name: user.first_name || "John",
        last_name: user.last_name || "Doe",
        email: user.email || "john.doe@example.com",
        status: "ACTIVE" as const,
        product_id: 1,
        product: mockProduct,
        product_price_id: 1,
        seat_label: event.has_seat_map ? t`Stalls · C-14` : null,
        order_id: 1,
    };

    const fallbackLocationDetails = {
        venue_name: t`Sample Venue`,
        address_line_1: t`123 Sample Street`,
    };
    const resolved = resolveEventLocation(event, null);
    const resolvedEventLocation = resolved?.type === LocationType.InPerson && resolved.location
        ? resolved
        : {
            id: 0,
            type: LocationType.InPerson,
            location: {name: fallbackLocationDetails.venue_name, structured_address: fallbackLocationDetails},
        };
    const eventWithDesignSettings = {
        ...event,
        event_location: resolvedEventLocation,
        settings: {
            ...eventSettings,
            ticket_design_settings: {
                accent_color: settings.accent_color,
                logo_image_id: logo?.id,
                footer_text: settings.footer_text,
                date_display_mode: settings.date_display_mode,
                enabled: settings.enabled
            },
        },
        images: logo ? [
            ...((event.images || []).filter(img => img.type !== 'TICKET_LOGO')),
            {
                id: logo.id,
                type: 'TICKET_LOGO' as const,
                url: logo.url,
                file_name: '',
                size: 0,
                mime_type: '',
            }
        ] : (event.images || []).filter(img => img.type !== 'TICKET_LOGO')
    };

    return (
        <div className={classes.previewWrapper}>
            <AttendeeTicket
                event={eventWithDesignSettings}
                attendee={mockAttendee}
                product={mockProduct}
                hideButtons={true}
            />
        </div>
    );
};
