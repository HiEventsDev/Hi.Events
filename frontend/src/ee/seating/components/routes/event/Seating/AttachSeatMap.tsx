import {useState} from "react";
import {Button, Group, Skeleton, Stack, Text} from "@mantine/core";
import {IconCircleCheckFilled} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {Link} from "react-router";
import {IdParam} from "../../../../../../types.ts";
import {useGetSeatMaps} from "../../../../queries/useGetSeatMaps.ts";
import {useUpdateEventSeatMap} from "../../../../mutations/useUpdateEventSeatMap.ts";
import {showError, showSuccess} from "../../../../../../utilites/notifications.tsx";
import {firstApiError} from "../../../../../../utilites/apiErrors.ts";
import {relativeDate} from "../../../../../../utilites/dates.ts";
import {PageBody} from "../../../../../../components/common/PageBody";
import {PageTitle} from "../../../../../../components/common/PageTitle";
import {Card} from "../../../../../../components/common/Card";
import {NoResultsSplash} from "../../../../../../components/common/NoResultsSplash";
import {SeatMapThumbnail} from "../../../SeatMapThumbnail";
import classes from "./Seating.module.scss";

interface AttachSeatMapProps {
    eventId: IdParam;
    organizerId: IdParam | undefined;
}

export const AttachSeatMap = ({eventId, organizerId}: AttachSeatMapProps) => {
    const seatMaps = useGetSeatMaps(organizerId).data;
    const mutation = useUpdateEventSeatMap(eventId);
    const [seatMapId, setSeatMapId] = useState<string | null>(null);
    const seatMapsPath = `/manage/organizer/${organizerId}/seat-maps`;

    const attach = () => mutation.mutate({action: 'attach', seatMapId: seatMapId as string}, {
        onSuccess: () => showSuccess(t`Seat map attached`),
        onError: error => showError(firstApiError(error, t`The seat map could not be attached`)),
    });

    return (
        <PageBody>
            <PageTitle subheading={t`Attach a venue seat map to sell reserved seats for this event.`}>{t`Seating`}</PageTitle>
            <Card>
                {!seatMaps && (
                    <div className={classes.chooserGrid}>
                        {[0, 1, 2].map(placeholder => <Skeleton key={placeholder} height={186} radius={12}/>)}
                    </div>
                )}

                {seatMaps?.length === 0 && (
                    <NoResultsSplash imageHref="/blank-slate/seat-maps.svg" heading={t`No seat maps yet`}
                                     subHeading={<p className={classes.blankSlateText}>
                                         {t`A seat map is a reusable venue layout of rows, tables and standing areas. Build one for your venue, then attach it to this event to sell seats.`}
                                     </p>}>
                        <Button component={Link} to={`${seatMapsPath}?create=1`}
                                data-testid="seating-create-seat-map-button">
                            {t`Create a seat map`}
                        </Button>
                    </NoResultsSplash>
                )}

                {seatMaps && seatMaps.length > 0 && (
                    <Stack>
                        <h3 className={classes.sectionTitle}>{t`Choose a seat map for this event`}</h3>
                        <Text className={classes.muted}>
                            {t`The event gets its own copy, so changes here never affect your other events.`}
                        </Text>

                        <div className={classes.chooserGrid}>
                            {seatMaps.map(seatMap => (
                                <button type="button" key={seatMap.id} className={classes.chooserOption}
                                        data-selected={seatMapId === String(seatMap.id)}
                                        data-testid={`seating-seat-map-option-${seatMap.id}`}
                                        onClick={() => setSeatMapId(String(seatMap.id))}>
                                    <div className={classes.chooserPreview}>
                                        <SeatMapThumbnail layout={seatMap.preview_layout}/>
                                    </div>
                                    <div className={classes.chooserName}>
                                        {seatMap.name}
                                        {seatMapId === String(seatMap.id) && <IconCircleCheckFilled size={18}/>}
                                    </div>
                                    <div className={classes.chooserMeta}>
                                        {t`${seatMap.seat_count} seats`} · {t`Updated ${relativeDate(seatMap.updated_at)}`}
                                    </div>
                                </button>
                            ))}
                        </div>

                        <Group>
                            <Button disabled={seatMapId === null} loading={mutation.isPending} data-testid="seating-attach-button"
                                    onClick={attach}>
                                {t`Attach seat map`}
                            </Button>
                            {organizerId && (
                                <Button variant="subtle" component={Link} to={seatMapsPath}>
                                    {t`Manage seat maps`}
                                </Button>
                            )}
                        </Group>
                    </Stack>
                )}
            </Card>
        </PageBody>
    );
};
