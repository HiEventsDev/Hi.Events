import {PageBody} from "../../../common/PageBody";
import {PageTitle} from "../../../common/PageTitle";
import {t, Trans} from "@lingui/macro";
import {useParams} from "react-router";
import {TableSkeleton} from "../../../common/TableSkeleton";
import {useDisclosure} from "@mantine/hooks";
import {ActionIcon, Button, Tooltip} from "@mantine/core";
import {IconCopy, IconPlus, IconQrcode} from "@tabler/icons-react";
import {useFilterQueryParamSync} from "../../../../hooks/useFilterQueryParamSync.ts";
import {QueryFilters} from "../../../../types.ts";
import {Pagination} from "../../../common/Pagination";
import {useGetEventCheckInLists} from "../../../../queries/useGetCheckInLists.ts";
import {useGetEvent} from "../../../../queries/useGetEvent.ts";
import {CheckInListTable, checkInListUrl, copyCheckInListLink} from "../../../common/CheckInListTable";
import {CreateCheckInListModal} from "../../../modals/CreateCheckInListModal";
import {Callout} from "../../../common/Callout";
import classes from "./CheckInLists.module.scss";

const CheckInLists = () => {
    const {eventId} = useParams();
    const {data: event} = useGetEvent(eventId);
    const [searchParams, setSearchParams] = useFilterQueryParamSync();
    const checkInListsQuery = useGetEventCheckInLists(
        eventId,
        searchParams as QueryFilters,
    );
    const checkInLists = checkInListsQuery?.data?.data;
    const pagination = checkInListsQuery?.data?.meta;
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);
    const defaultCheckInList = checkInLists?.find(checkInList => checkInList.is_system_default);

    return (
        <PageBody>
            <PageTitle
                subheading={t`Scan tickets at the door with a phone, tablet, or USB scanner.`}
            >
                {t`Check-In Lists`}
            </PageTitle>

            <Callout variant="info" title={t`Every event has a check-in list`}>
                <Trans>
                    Door staff open the check-in link on any device — no account needed. Treat the link like a shared
                    password. Add more check-in lists to split entry by entrance, date, or ticket type.
                </Trans>
            </Callout>

            <div className={classes.actions}>
                <Button
                    leftSection={<IconQrcode/>}
                    color={'green'}
                    size={'sm'}
                    disabled={!defaultCheckInList}
                    data-testid="check-in-open-default-button"
                    onClick={() => defaultCheckInList
                        && window.open(checkInListUrl(defaultCheckInList.short_id), '_blank')}
                >
                    {t`Open Check-In`}
                </Button>
                <Tooltip label={t`Copy check-in link`}>
                    <ActionIcon
                        variant="light"
                        size="lg"
                        disabled={!defaultCheckInList}
                        aria-label={t`Copy check-in link`}
                        onClick={() => defaultCheckInList && copyCheckInListLink(defaultCheckInList.short_id)}
                    >
                        <IconCopy size={18}/>
                    </ActionIcon>
                </Tooltip>
                <Button
                    leftSection={<IconPlus/>}
                    variant={'light'}
                    size={'sm'}
                    data-testid="checkin-list-create-button"
                    onClick={openCreateModal}>{t`Create Check-In List`}
                </Button>
            </div>

            <TableSkeleton isVisible={!checkInLists || checkInListsQuery.isFetching}/>

            {checkInLists && <CheckInListTable
                checkInLists={checkInLists}
                openCreateModal={openCreateModal}
                event={event}
            />}

            {createModalOpen && <CreateCheckInListModal onClose={closeCreateModal}/>}

            {!!checkInLists?.length && (
                <Pagination value={searchParams.pageNumber}
                            onChange={(value) => setSearchParams({pageNumber: value})}
                            total={Number(pagination?.last_page)}
                />
            )}
        </PageBody>
    );
}

export default CheckInLists;
