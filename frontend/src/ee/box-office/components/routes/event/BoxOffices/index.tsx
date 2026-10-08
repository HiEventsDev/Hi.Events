import {PageBody} from "../../../../../../components/common/PageBody";
import {PageTitle} from "../../../../../../components/common/PageTitle";
import {t, Trans} from "@lingui/macro";
import {useParams} from "react-router";
import {TableSkeleton} from "../../../../../../components/common/TableSkeleton";
import {useDisclosure} from "@mantine/hooks";
import {ActionIcon, Anchor, Button, Tooltip} from "@mantine/core";
import {IconCashRegister, IconCopy, IconPlus} from "@tabler/icons-react";
import {useFilterQueryParamSync} from "../../../../../../hooks/useFilterQueryParamSync.ts";
import {QueryFilters} from "../../../../../../types.ts";
import {Pagination} from "../../../../../../components/common/Pagination";
import {useGetBoxOffices} from "../../../../queries/useGetBoxOffices.ts";
import {useGetEvent} from "../../../../../../queries/useGetEvent.ts";
import {useGetMe} from "../../../../../../queries/useGetMe.ts";
import {useGetOrganizerTerminalReaders} from "../../../../queries/useGetOrganizerTerminalReaders.ts";
import {BoxOfficeTable, copyBoxOfficeLink} from "../../../common/BoxOfficeTable";
import {CreateBoxOfficeModal} from "../../../modals/CreateBoxOfficeModal";
import {AddTerminalReaderModal} from "../../../modals/AddTerminalReaderModal";
import {useBoxOfficePin} from "../../../../hooks/useBoxOfficePin.tsx";
import {Callout} from "../../../../../../components/common/Callout";
import classes from "./BoxOffices.module.scss";
import {useLicensedFeature} from "../../../../../licensing/hooks/useLicensedFeature.ts";
import {LicenceLockedCallout} from "../../../../../licensing/components/LicenceLockedCallout";
import {FeatureFlag} from "../../../../../../constants/featureFlags.ts";

const BoxOffices = () => {
    const {eventId} = useParams();
    const {data: event} = useGetEvent(eventId);
    const [searchParams, setSearchParams] = useFilterQueryParamSync();
    const boxOfficesQuery = useGetBoxOffices(eventId, searchParams as QueryFilters);
    const boxOffices = boxOfficesQuery?.data?.data;
    const pagination = boxOfficesQuery?.data?.meta;
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);
    const [addReaderModalOpen, {open: openAddReaderModal, close: closeAddReaderModal}] = useDisclosure(false);
    const organizerId = event?.organizer_id;
    const {data: me} = useGetMe();
    const canManageReaders = me?.role === 'ADMIN' || me?.role === 'SUPERADMIN';
    const readersQuery = useGetOrganizerTerminalReaders(organizerId, !!organizerId && canManageReaders);
    const readers = readersQuery.data;
    const {openBoxOffice, pinModalElement} = useBoxOfficePin();
    const needsReader = !!readers?.stripe_connected && readers.readers.length === 0;
    const defaultBoxOffice = boxOffices?.find(boxOffice => boxOffice.is_system_default);
    const isLocked = useLicensedFeature(FeatureFlag.BOX_OFFICE).isSetupLocked;

    return (
        <PageBody>
            <PageTitle
                subheading={t`Sell tickets at the door with cash, comps, or a card reader.`}
            >
                {t`Box Office`}
            </PageTitle>

            {isLocked ? <LicenceLockedCallout/> : (
                <>
                    <Callout variant="info" title={t`Every event has a box office`}>
                        <Trans>
                            Door staff sign in with the box office link and a PIN — no account needed. Treat the two like a shared password, and reset the PIN if it gets out. Add more box offices to split sales by entrance, date, or product.
                        </Trans>
                        {needsReader && (
                            <div className={classes.readerNote}>
                                <Trans>
                                    No card reader is paired, so staff can take cash and comps but not cards.
                                </Trans>
                                {' '}
                                <Anchor
                                    component="button"
                                    type="button"
                                    className={classes.readerLink}
                                    data-testid="box-office-add-reader-button"
                                    onClick={openAddReaderModal}
                                >
                                    {t`Add a card reader`}
                                </Anchor>
                            </div>
                        )}
                    </Callout>

                    <div className={classes.actions}>
                        <Button
                            leftSection={<IconCashRegister/>}
                            color={'green'}
                            size={'sm'}
                            disabled={!defaultBoxOffice}
                            data-testid="box-office-open-default-button"
                            onClick={() => defaultBoxOffice && openBoxOffice(defaultBoxOffice)}
                        >
                            {t`Open Box Office`}
                        </Button>
                        <Tooltip label={t`Copy box office link`}>
                            <ActionIcon
                                variant="light"
                                size="lg"
                                disabled={!defaultBoxOffice}
                                aria-label={t`Copy box office link`}
                                onClick={() => defaultBoxOffice && copyBoxOfficeLink(defaultBoxOffice.short_id)}
                            >
                                <IconCopy size={18}/>
                            </ActionIcon>
                        </Tooltip>
                        <Button
                            leftSection={<IconPlus/>}
                            variant={'light'}
                            size={'sm'}
                            data-testid="box-office-create-button"
                            onClick={openCreateModal}>{t`Create Box Office`}
                        </Button>
                    </div>
                </>
            )}

            <TableSkeleton isVisible={!boxOffices || boxOfficesQuery.isFetching}/>

            {boxOffices && <BoxOfficeTable
                boxOffices={boxOffices}
                openCreateModal={openCreateModal}
                event={event}
                isLocked={isLocked}
            />}

            {createModalOpen && <CreateBoxOfficeModal onClose={closeCreateModal}/>}

            {pinModalElement}

            {addReaderModalOpen && organizerId
                && <AddTerminalReaderModal onClose={closeAddReaderModal} organizerId={organizerId}/>}

            {!!boxOffices?.length && (
                <Pagination value={searchParams.pageNumber}
                            onChange={(value) => setSearchParams({pageNumber: value})}
                            total={Number(pagination?.last_page)}
                />
            )}
        </PageBody>
    );
}

export default BoxOffices;
