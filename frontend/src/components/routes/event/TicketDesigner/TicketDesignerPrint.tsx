import {useParams} from 'react-router';
import {useGetEvent} from '../../../../queries/useGetEvent.ts';
import {useGetMe} from '../../../../queries/useGetMe.ts';
import {useGetEventSettings} from '../../../../queries/useGetEventSettings.ts';
import {useGetEventImages} from '../../../../queries/useGetEventImages.ts';
import {AttendeeTicket} from '../../../common/AttendeeTicket';
import {PoweredByFooter} from '../../../common/PoweredByFooter';
import {t} from '@lingui/macro';
import {useEffect} from "react";
import classes from '../../../routes/product-widget/PrintOrder/PrintOrder.module.scss';

const TicketDesignerPrint = () => {
    const {eventId} = useParams();
    const eventQuery = useGetEvent(eventId);
    const meQuery = useGetMe();
    const settingsQuery = useGetEventSettings(eventId);
    const imagesQuery = useGetEventImages(eventId);

    const event = eventQuery.data;
    const user = meQuery.data;
    const settings = settingsQuery.data;
    const images = imagesQuery.data;

    useEffect(() => {
        if (event && user && settings) {
            setTimeout(() => window?.print(), 500);
        }
    }, [event, user, settings]);

    if (!event || !user || !settings) {
        return null;
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
            public_id: "ORD123",
            created_at: new Date().toISOString(),
            total_gross: 2500,
            currency: event.currency || "USD",
        }
    };

    // Merge the ticket design settings and images into the event
    const eventWithDesignSettings = {
        ...event,
        settings: {
            ...event.settings,
            ticket_design_settings: settings.ticket_design_settings,
            location_details: event.settings?.location_details || {
                venue_name: t`Sample Venue`,
                address_line_1: t`123 Sample Street`,
            }
        },
        images: images || []
    };

    return (
        <div className={classes.container}>
            <h2 className={classes.title}>{t`Ticket Preview for`} {event.title}</h2>
            <div className={classes.ticketPage}>
                <AttendeeTicket
                    attendee={mockAttendee}
                    product={mockProduct}
                    event={eventWithDesignSettings}
                    hideButtons
                />
                <div className={classes.poweredBy}>
                    <PoweredByFooter/>
                </div>
            </div>
        </div>
    );
}

export default TicketDesignerPrint;
