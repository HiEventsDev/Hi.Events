import {useMemo, useState} from "react";
import {Alert, Button, Group, Stack} from "@mantine/core";
import {IconArmchair, IconPencil, IconRefresh} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {Link, useParams} from "react-router";
import {IdParam, Product} from "../../../../../../types.ts";
import {SeatMapDiff, seatMapClient} from "../../../../api/seat-map.client.ts";
import {useGetEvent} from "../../../../../../queries/useGetEvent.ts";
import {useGetEventSeatMap} from "../../../../queries/useGetEventSeatMap.ts";
import {useUpdateEventSeatMap} from "../../../../mutations/useUpdateEventSeatMap.ts";
import {confirmationDialog} from "../../../../../../utilites/confirmationDialog.tsx";
import {showError, showSuccess} from "../../../../../../utilites/notifications.tsx";
import {firstApiError} from "../../../../../../utilites/apiErrors.ts";
import {PageBody} from "../../../../../../components/common/PageBody";
import {PageTitle} from "../../../../../../components/common/PageTitle";
import {indexLayout} from "../../../lib/layoutIndex.ts";
import {SeatMapRenderer} from "../../../SeatMapRenderer";
import {AttachSeatMap} from "./AttachSeatMap.tsx";
import {BandProductsCard} from "./BandProductsCard.tsx";
import {SeatingRulesCard} from "./SeatingRulesCard.tsx";
import {SyncFromSourceModal} from "./SyncFromSourceModal.tsx";
import classes from "./Seating.module.scss";
import {useLicensedFeature} from "../../../../../licensing/hooks/useLicensedFeature.ts";
import {LicenceLockedCallout} from "../../../../../licensing/components/LicenceLockedCallout";
import {FeatureFlag} from "../../../../../../constants/featureFlags.ts";

export default function Seating() {
    const {eventId} = useParams();
    const event = useGetEvent(eventId).data;
    const eventSeatMapQuery = useGetEventSeatMap(eventId as IdParam);
    const eventSeatMap = eventSeatMapQuery.data;
    const detachMutation = useUpdateEventSeatMap(eventId as IdParam);
    const [diff, setDiff] = useState<SeatMapDiff | null>(null);
    const isLocked = useLicensedFeature(FeatureFlag.SEATING).isSetupLocked;

    const index = useMemo(() => eventSeatMap ? indexLayout(eventSeatMap.layout) : null, [eventSeatMap]);

    const capacityByBand = useMemo(() => {
        const capacity: Record<string, number> = {};
        index?.seats.forEach(({seat}) => capacity[seat.band] = (capacity[seat.band] ?? 0) + 1);
        index?.zones.forEach(({zone}) => capacity[zone.band] = (capacity[zone.band] ?? 0) + zone.capacity);
        return capacity;
    }, [index]);

    const seatableProducts = useMemo(() => (event?.product_categories ?? [])
        .flatMap(category => category.products ?? [])
        .filter((product: Product) => product.product_type === 'TICKET' && product.type !== 'DONATION'), [event]);

    const reviewSourceChanges = () => seatMapClient.syncFromSource(eventId as IdParam, true)
        .then(response => setDiff(response.data))
        .catch(error => showError(firstApiError(error, t`The venue seat map could not be compared`)));

    const detach = () => confirmationDialog(
        t`Remove the seat map from this event? Tickets go back to being sold by quantity.`,
        () => detachMutation.mutate({action: 'detach'}, {
            onSuccess: () => showSuccess(t`Seat map removed`),
            onError: error => showError(firstApiError(error, t`The seat map could not be removed`)),
        }),
    );

    if (eventSeatMapQuery.isLoading) {
        return <PageBody><PageTitle>{t`Seating`}</PageTitle></PageBody>;
    }

    if (!eventSeatMap || !index) {
        return isLocked
            ? <PageBody><PageTitle>{t`Seating`}</PageTitle><LicenceLockedCallout/></PageBody>
            : <AttachSeatMap eventId={eventId as IdParam} organizerId={event?.organizer_id}/>;
    }

    const rules = {
        prevent_orphan_seats: eventSeatMap.prevent_orphan_seats,
        max_seats_per_order: eventSeatMap.max_seats_per_order,
        allow_seat_change: eventSeatMap.allow_seat_change,
    };

    return (
        <PageBody>
            <PageTitle subheading={eventSeatMap.source_seat_map
                ? t`Based on ${eventSeatMap.source_seat_map.name}. This event keeps its own copy.`
                : t`This event keeps its own copy of the seat map.`}>
                {t`Seating`}
            </PageTitle>

            <Stack>
                {isLocked && <LicenceLockedCallout/>}

                {!isLocked && eventSeatMap.is_update_available_from_source && (
                    <Alert icon={<IconRefresh size={18}/>} color="blue" title={t`The venue seat map has changed`}>
                        <Button size="xs" variant="light" onClick={reviewSourceChanges} data-testid="seating-sync-source-button">
                            {t`Review changes`}
                        </Button>
                    </Alert>
                )}

                <div className={classes.preview}>
                    <SeatMapRenderer area={eventSeatMap.layout.areas[0]} bands={index.bands}/>
                </div>

                <BandProductsCard
                    key={JSON.stringify(eventSeatMap.band_products)}
                    eventId={eventId as IdParam}
                    bands={eventSeatMap.layout.bands}
                    bandProducts={eventSeatMap.band_products}
                    capacityByBand={capacityByBand}
                    seatableProducts={seatableProducts}
                    currency={event?.currency ?? 'USD'}
                    isLocked={isLocked}
                />

                <SeatingRulesCard key={JSON.stringify(rules)} eventId={eventId as IdParam} rules={rules} isLocked={isLocked}/>

                <Group justify="space-between">
                    <Group>
                        <Button leftSection={<IconArmchair size={16}/>} component={Link}
                                to={`/manage/event/${eventId}/seating/sales`} data-testid="seating-sales-button">
                            {t`Seat sales and held seats`}
                        </Button>
                        {!isLocked && (
                            <Button variant="default" leftSection={<IconPencil size={16}/>} component={Link}
                                    to={`/manage/event/${eventId}/seating/designer`} data-testid="seating-edit-map-button">
                                {t`Edit this event's seat map`}
                            </Button>
                        )}
                    </Group>
                    <Button variant="subtle" color="red" loading={detachMutation.isPending} data-testid="seating-detach-button"
                            onClick={detach}>
                        {t`Remove seat map`}
                    </Button>
                </Group>
            </Stack>

            <SyncFromSourceModal eventId={eventId as IdParam} diff={diff} onClose={() => setDiff(null)}/>
        </PageBody>
    );
}
