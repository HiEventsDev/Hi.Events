import {ActionIcon, Menu, VisuallyHidden} from "@mantine/core";
import {WriteAccess} from "../WriteAccess";
import {CreateButton} from "../CreateButton";
import {IconDotsVertical, IconPencil, IconPlus, IconTrash} from "@tabler/icons-react";
import {useDisclosure} from "@mantine/hooks";
import {useMemo, useState} from "react";
import {useParams} from "react-router";
import {t} from "@lingui/macro";
import {TanStackTable, TanStackTableColumn} from "../TanStackTable";
import {CellText, EmptyCell, IdentityCell} from "../TanStackTable/cells.tsx";
import {sortableHeader, TableSort} from "../TanStackTable/sorting.tsx";
import {NoResultsSplash} from "../NoResultsSplash";
import {IdParam, Location} from "../../../types";
import {useDeleteLocation} from "../../../mutations/useDeleteLocation.ts";
import {confirmationDialog} from "../../../utilites/confirmationDialog.tsx";
import {formatAddress} from "../../../utilites/addressUtilities.ts";
import {relativeDate} from "../../../utilites/dates.ts";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {LocationEditModal} from "../../modals/LocationEditModal";

interface LocationsTableProps {
    locations: Location[];
    openCreateModal: () => void;
    isRefreshing?: boolean;
    sort?: TableSort;
}

export const LocationsTable = ({locations, openCreateModal, isRefreshing, sort}: LocationsTableProps) => {
    const {organizerId} = useParams();
    const [editModalOpen, {open: openEditModal, close: closeEditModal}] = useDisclosure(false);
    const [selectedLocation, setSelectedLocation] = useState<Location | null>(null);
    const deleteMutation = useDeleteLocation();

    const locationName = (location: Location) =>
        location.name ?? location.structured_address?.venue_name ?? t`Unnamed`;

    const handleDelete = (location: Location) => {
        confirmationDialog(
            t`Delete "${locationName(location)}"?`,
            () => deleteMutation.mutate(
                {organizerId: organizerId as IdParam, locationId: location.id as IdParam},
                {
                    onSuccess: () => showSuccess(t`Location deleted`),
                    onError: (error: any) =>
                        showError(error?.response?.data?.message ?? t`Could not delete location`),
                },
            ),
            {confirm: t`Delete`},
        );
    };

    const columns = useMemo<TanStackTableColumn<Location>[]>(() => [
        {
            id: 'name',
            enableHiding: false,
            ...sortableHeader(t`Name`, 'name', sort, 'asc'),
            meta: {label: t`Name`, sticky: 'left'},
            cell: ({row: {original: location}}) => (
                <IdentityCell
                    stacked
                    primary={<CellText strong title={locationName(location)}>{locationName(location)}</CellText>}
                    secondary={formatAddress(location.structured_address ?? {})}
                />
            ),
        },
        {
            id: 'created',
            ...sortableHeader(t`Created`, 'created_at', sort),
            meta: {label: t`Created`},
            cell: ({row: {original: location}}) => location.created_at
                ? <span title={location.created_at}>{relativeDate(location.created_at)}</span>
                : <EmptyCell/>,
        },
        {
            id: 'actions',
            header: () => <VisuallyHidden>{t`Actions`}</VisuallyHidden>,
            enableHiding: false,
            meta: {sticky: 'right'},
            cell: ({row: {original: location}}) => (
                <WriteAccess>
                    <Menu shadow="md" width={200} position="bottom-end">
                        <Menu.Target>
                            <ActionIcon variant="subtle" color="gray" aria-label={t`Actions`}>
                                <IconDotsVertical size={18}/>
                            </ActionIcon>
                        </Menu.Target>

                        <Menu.Dropdown>
                            <Menu.Label>{t`Manage`}</Menu.Label>
                            <Menu.Item
                                leftSection={<IconPencil size={14}/>}
                                onClick={() => {
                                    setSelectedLocation(location);
                                    openEditModal();
                                }}
                            >
                                {t`Edit location`}
                            </Menu.Item>
                            <Menu.Divider/>
                            <Menu.Label>{t`Danger zone`}</Menu.Label>
                            <Menu.Item
                                color="red"
                                leftSection={<IconTrash size={14}/>}
                                onClick={() => handleDelete(location)}
                            >
                                {t`Delete location`}
                            </Menu.Item>
                        </Menu.Dropdown>
                    </Menu>
                </WriteAccess>
            ),
        },
    ], [sort?.sortBy, sort?.sortDirection]);

    if (locations.length === 0) {
        return (
            <NoResultsSplash
                heading={t`No Saved Locations`}
                imageHref={'/blank-slate/occurrence-schedule.svg'}
                subHeading={(
                    <>
                        <p>
                            {t`Reusable venues appear here automatically as you create events with addresses, and you can add your own.`}
                        </p>
                        <CreateButton
                            size={'xs'}
                            leftSection={<IconPlus/>}
                            color={'green'}
                            onClick={() => openCreateModal()}>{t`Add Location`}
                        </CreateButton>
                    </>
                )}
            />
        );
    }

    return (
        <>
            <TanStackTable rowLines={2} data={locations} columns={columns} noCard isRefreshing={isRefreshing}/>

            {(editModalOpen && selectedLocation) && (
                <LocationEditModal
                    onClose={() => {
                        setSelectedLocation(null);
                        closeEditModal();
                    }}
                    organizerId={organizerId as IdParam}
                    location={selectedLocation}
                />
            )}
        </>
    );
};
