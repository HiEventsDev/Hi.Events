import {useGetEvent} from "../../../../queries/useGetEvent.ts";
import {useGetMe} from "../../../../queries/useGetMe.ts";
import {t} from "@lingui/macro";
import {IdParam} from "../../../../types.ts";
import {AttendeeTicket} from "../../../common/AttendeeTicket";
import classes from './TicketPreview.module.scss';
import {useGetEventSettings} from "../../../../queries/useGetEventSettings.ts";

interface TicketDesignSettings {
    accent_color: string;
    logo_image_id: IdParam | null;
    footer_text: string | null;
    enabled: boolean;
}

interface TicketPreviewProps {
    settings: TicketDesignSettings;
    eventId: IdParam;
    logoUrl?: string;
}

export const TicketPreview = ({settings, eventId, logoUrl}: TicketPreviewProps) => {
    const eventQuery = useGetEvent(eventId);
    const meQuery = useGetMe();
    const eventSettingsQuery = useGetEventSettings(eventId);

    const event = eventQuery.data;
    const user = meQuery.data;
    const eventSettings = eventSettingsQuery.data;

    if (!event || !user) {
        return (
            <div className={classes.loadingState}>
                <p>{t`Loading preview...`}</p>
            </div>
        );
    }

    const mockProduct = {
        id: 1,
        title: t`General Admission`,
        price: 2500,
        type: "TICKET" as const,
        sale_start_date: null,
        sale_end_date: null,
        max_per_order: null,
        min_per_order: null,
        quantity_available: null,
        is_hidden: false,
        sort_order: 1,
        description: "",
        is_hidden_without_promo_code: false
    };

    const mockAttendee = {
        id: 1,
        public_id: "PREVIEW12345",
        short_id: "P1234",
        first_name: user.first_name || "John",
        last_name: user.last_name || "Doe",
        email: user.email || "john.doe@example.com",
        status: "ACTIVE" as const,
        checked_in_at: null,
        product_id: mockProduct.id,
        product: mockProduct,
        product_price_id: 1,
        order_id: 1,
        order: {
            id: 1,
            short_id: "ORD123",
            created_at: new Date().toISOString(),
            total_gross: 2500,
            currency: event.currency || "USD",
        }
    };

    const eventWithDesignSettings = {
        ...event,
        settings: {
            ...event.settings,
            ticket_design_settings: {
                accent_color: settings.accent_color,
                logo_image_id: settings.logo_image_id,
                footer_text: settings.footer_text,
                enabled: settings.enabled
            },
            location_details: eventSettings?.location_details || {
                venue_name: t`Sample Venue`,
                address_line_1: t`123 Sample Street`,
            }
        },
        images: logoUrl && settings.logo_image_id ? [
            ...((event.images || []).filter(img => img.type !== 'TICKET_LOGO')),
            {
                id: settings.logo_image_id,
                type: 'TICKET_LOGO' as const,
                url: logoUrl,
                size_bytes: 0,
                filename: ''
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
