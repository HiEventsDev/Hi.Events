import {useParams} from 'react-router';
import {useGetEvent} from '../../../../queries/useGetEvent.ts';
import {useGetMe} from '../../../../queries/useGetMe.ts';
import {useGetEventSettings} from '../../../../queries/useGetEventSettings.ts';
import {useGetEventImages} from '../../../../queries/useGetEventImages.ts';
import {AttendeeTicket} from '../../../common/AttendeeTicket';
import {PoweredByFooter} from '../../../common/PoweredByFooter';
import {useBrandingSubscription} from '../../../../hooks/useBrandingSubscription.ts';
import {t} from '@lingui/macro';
import {useEffect} from "react";
import {resolveEventLocation} from "../../../../utilites/effectiveLocation.ts";
import {Attendee, LocationType, Product, ProductPriceType, ProductType} from "../../../../types.ts";
import classes from '../../../routes/product-widget/PrintOrder/PrintOrder.module.scss';

const TicketDesignerPrint = () => {
    const {eventId} = useParams();
    const {isActive, isSettled} = useBrandingSubscription();
    const eventQuery = useGetEvent(eventId);
    const meQuery = useGetMe();
    const settingsQuery = useGetEventSettings(eventId);
    const imagesQuery = useGetEventImages(eventId);

    const event = eventQuery.data;
    const user = meQuery.data;
    const settings = settingsQuery.data;
    const images = imagesQuery.data;

    useEffect(() => {
        if (event && user && settings && isSettled) {
            setTimeout(() => window?.print(), 500);
        }
    }, [event, user, settings, isSettled]);

    if (!event || !user || !settings) {
        return null;
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
        settings,
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
                    <PoweredByFooter brandingRemoved={isActive}/>
                </div>
            </div>
        </div>
    );
}

export default TicketDesignerPrint;
