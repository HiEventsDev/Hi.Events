import {useMemo, useState} from "react";
import {ActionIcon, Button, Modal, Stack, TextInput} from "@mantine/core";
import {useDisclosure} from "@mantine/hooks";
import {IconPencil, IconPencilPlus, IconPlus, IconTrash} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {Link, useNavigate, useParams, useSearchParams} from "react-router";
import {IdParam} from "../../../../../../types.ts";
import {useGetSeatMaps} from "../../../../queries/useGetSeatMaps.ts";
import {useCreateSeatMap} from "../../../../mutations/useCreateSeatMap.ts";
import {useDeleteSeatMap} from "../../../../mutations/useDeleteSeatMap.ts";
import {confirmationDialog} from "../../../../../../utilites/confirmationDialog.tsx";
import {showError, showSuccess} from "../../../../../../utilites/notifications.tsx";
import {firstApiError} from "../../../../../../utilites/apiErrors.ts";
import {relativeDate} from "../../../../../../utilites/dates.ts";
import {PageBody} from "../../../../../../components/common/PageBody";
import {useLicensedFeature} from "../../../../../licensing/hooks/useLicensedFeature.ts";
import {LicenceLockedCallout} from "../../../../../licensing/components/LicenceLockedCallout";
import {FeatureFlag} from "../../../../../../constants/featureFlags.ts";
import {PageTitle} from "../../../../../../components/common/PageTitle";
import {Card} from "../../../../../../components/common/Card";
import {NoResultsSplash} from "../../../../../../components/common/NoResultsSplash";
import {TableSkeleton} from "../../../../../../components/common/TableSkeleton";
import {countSeats} from "../../../lib/generateSeats.ts";
import {createSeatMapFromTemplate, SEAT_MAP_TEMPLATE_NAMES, SeatMapTemplateName} from "../../../lib/templates.ts";
import {SeatMapThumbnail} from "../../../SeatMapThumbnail";
import classes from "./SeatMaps.module.scss";

const TEMPLATE_COPY: Record<Exclude<SeatMapTemplateName, 'empty'>, () => {name: string; description: string}> = {
    theatre: () => ({name: t`Theatre`, description: t`Curved rows and a balcony.`}),
    banquet: () => ({name: t`Banquet`, description: t`Round tables for dinners.`}),
    club: () => ({name: t`Club`, description: t`Standing floor and mezzanine.`}),
    conference: () => ({name: t`Conference`, description: t`Straight rows, central aisle.`}),
    thrust: () => ({name: t`Thrust`, description: t`Seats on three sides.`}),
};

const TEMPLATE_NAMES = SEAT_MAP_TEMPLATE_NAMES.filter(
    (name): name is Exclude<SeatMapTemplateName, 'empty'> => name !== 'empty',
);

const CreateSeatMapModal = ({organizerId, onClose}: {organizerId: IdParam; onClose: () => void}) => {
    const navigate = useNavigate();
    const [name, setName] = useState('');
    const [template, setTemplate] = useState<SeatMapTemplateName>('empty');
    const createMutation = useCreateSeatMap();
    const templates = useMemo(() => TEMPLATE_NAMES.map(templateName => ({
        name: templateName,
        layout: createSeatMapFromTemplate(templateName),
    })), []);

    const create = () => createMutation.mutate(
        {organizerId, name, layout: createSeatMapFromTemplate(template)},
        {
            onSuccess: ({data}) => {
                showSuccess(t`Seat map created`);
                navigate(`/manage/organizer/${organizerId}/seat-maps/${data.id}`);
            },
            onError: (error: unknown) => showError(firstApiError(error, t`The seat map could not be created`)),
        },
    );

    return (
        <Modal opened onClose={onClose} title={t`Create a seat map`} size="xl">
            <Stack>
                <TextInput label={t`Name`} placeholder={t`Main auditorium`} required value={name}
                           onChange={event => setName(event.currentTarget.value)}/>

                <div>
                    <h3 className={classes.chooserHeading}>{t`How would you like to start?`}</h3>

                    <button type="button" className={classes.scratchOption} data-selected={template === 'empty'}
                            data-testid="seat-map-template-option-empty" onClick={() => setTemplate('empty')}>
                        <IconPencilPlus size={26}/>
                        <span>
                            <span className={classes.optionName}>{t`Start from scratch`}</span>
                            <span className={classes.muted}>
                                {t`An empty plan. Draw your own rows, tables and standing areas.`}
                            </span>
                        </span>
                    </button>

                    <div className={classes.templatesHeading}>{t`Or start from a venue like yours`}</div>

                    <div className={classes.templates}>
                        {templates.map(({name: templateName, layout}) => {
                            const copy = TEMPLATE_COPY[templateName]();
                            return (
                                <button type="button" key={templateName} className={classes.template}
                                        data-selected={template === templateName}
                                        data-testid={`seat-map-template-option-${templateName}`}
                                        onClick={() => setTemplate(templateName)}>
                                    <div className={classes.templatePreview}>
                                        <SeatMapThumbnail layout={layout}/>
                                    </div>
                                    <div className={classes.templateLabel}>{copy.name}</div>
                                    <div className={classes.templateDescription}>
                                        {t`${countSeats(layout)} seats`} · {copy.description}
                                    </div>
                                </button>
                            );
                        })}
                    </div>
                </div>

                <Button onClick={create} disabled={name.trim() === ''} loading={createMutation.isPending}
                        data-testid="seat-map-create-submit-button">
                    {t`Create and start designing`}
                </Button>
                <p className={classes.footNote}>{t`You can change everything in the designer afterwards.`}</p>
            </Stack>
        </Modal>
    );
};

export default function SeatMaps() {
    const {organizerId} = useParams();
    const seatMaps = useGetSeatMaps(organizerId).data;
    const deleteMutation = useDeleteSeatMap();
    const [searchParams, setSearchParams] = useSearchParams();
    const [createOpened, {open, close}] = useDisclosure(searchParams.get('create') === '1');
    const isLocked = useLicensedFeature(FeatureFlag.SEATING).isSetupLocked;

    const closeCreate = () => {
        close();
        if (searchParams.has('create')) {
            searchParams.delete('create');
            setSearchParams(searchParams, {replace: true});
        }
    };

    const remove = (seatMapId: number) => confirmationDialog(
        t`Delete this seat map? Events that already use it keep their own copy.`,
        () => deleteMutation.mutate({organizerId: organizerId as IdParam, seatMapId}, {
            onSuccess: () => showSuccess(t`Seat map deleted`),
            onError: (error: unknown) => showError(firstApiError(error, t`The seat map could not be deleted`)),
        }),
    );

    return (
        <PageBody>
            <PageTitle subheading={t`Reusable venue layouts. Attach one to an event to sell reserved seats.`}>
                {t`Seat Maps`}
            </PageTitle>

            {isLocked && <LicenceLockedCallout/>}

            {!isLocked && seatMaps && seatMaps.length > 0 && (
                <Button color="green" size="sm" mb="md" onClick={open} rightSection={<IconPlus/>}
                        data-testid="seat-map-create-button">
                    {t`Create seat map`}
                </Button>
            )}

            <TableSkeleton isVisible={!seatMaps}/>

            {seatMaps?.length === 0 && (
                <NoResultsSplash imageHref="/blank-slate/seat-maps.svg" heading={t`No seat maps yet`}
                                 subHeading={<p>{t`Start from a template such as a theatre, banquet or club layout, then attach the map to any event.`}</p>}>
                    {!isLocked && (
                        <Button color="green" onClick={open} rightSection={<IconPlus/>}
                                data-testid="seat-map-create-button">
                            {t`Create seat map`}
                        </Button>
                    )}
                </NoResultsSplash>
            )}

            <div className={classes.grid}>
                {seatMaps?.map(seatMap => (
                    <Card key={seatMap.id} className={classes.mapCard}>
                        {isLocked ? (
                            <div className={classes.mapPreview}>
                                <SeatMapThumbnail layout={seatMap.preview_layout}/>
                            </div>
                        ) : (
                            <Link to={`/manage/organizer/${organizerId}/seat-maps/${seatMap.id}`} className={classes.mapPreview}
                                  aria-label={t`Edit ${seatMap.name}`} tabIndex={-1}>
                                <SeatMapThumbnail layout={seatMap.preview_layout}/>
                            </Link>
                        )}
                        <div className={classes.mapDetails}>
                            <h3 className={classes.mapName}>{seatMap.name}</h3>
                            <div className={classes.muted}>
                                {t`${seatMap.seat_count} seats`} · {t`Updated ${relativeDate(seatMap.updated_at)}`}
                            </div>
                        </div>
                        <div className={classes.mapActions}>
                            {!isLocked && (
                                <Button size="xs" variant="default" leftSection={<IconPencil size={14}/>} component={Link}
                                        to={`/manage/organizer/${organizerId}/seat-maps/${seatMap.id}`}
                                        data-testid={`seat-map-edit-button-${seatMap.id}`}>
                                    {t`Edit`}
                                </Button>
                            )}
                            <ActionIcon variant="subtle" color="red" aria-label={t`Delete ${seatMap.name}`}
                                        data-testid={`seat-map-delete-button-${seatMap.id}`}
                                        onClick={() => remove(seatMap.id)}>
                                <IconTrash size={18}/>
                            </ActionIcon>
                        </div>
                    </Card>
                ))}
            </div>

            {createOpened && !isLocked && <CreateSeatMapModal organizerId={organizerId as IdParam} onClose={closeCreate}/>}
        </PageBody>
    );
}
