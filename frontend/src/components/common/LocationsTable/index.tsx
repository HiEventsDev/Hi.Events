import {Button, Group, Menu, Table as MantineTable, Text} from "@mantine/core";
import {IconDotsVertical, IconPencil, IconPlus, IconTrash} from "@tabler/icons-react";
import {useDisclosure} from "@mantine/hooks";
import {useState} from "react";
import {useParams} from "react-router";
import {t} from "@lingui/macro";
import {Table, TableHead} from "../Table";
import {NoResultsSplash} from "../NoResultsSplash";
import Truncate from "../Truncate";
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
}

export const LocationsTable = ({locations, openCreateModal}: LocationsTableProps) => {
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

    const ActionMenu = ({location}: { location: Location }) => (
        <Group wrap="nowrap" gap={0} justify="flex-end">
            <Menu shadow="md" width={200}>
                <Menu.Target>
                    <Button size="xs" variant="transparent">
                        <IconDotsVertical/>
                    </Button>
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
        </Group>
    );

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
                        <Button
                            size={'xs'}
                            leftSection={<IconPlus/>}
                            color={'green'}
                            onClick={() => openCreateModal()}>{t`Add Location`}
                        </Button>
                    </>
                )}
            />
        );
    }

    return (
        <>
            <Table>
                <TableHead>
                    <MantineTable.Tr>
                        <MantineTable.Th>{t`Name`}</MantineTable.Th>
                        <MantineTable.Th>{t`Address`}</MantineTable.Th>
                        <MantineTable.Th>{t`Created`}</MantineTable.Th>
                        <MantineTable.Th></MantineTable.Th>
                    </MantineTable.Tr>
                </TableHead>
                <MantineTable.Tbody>
                    {locations.map((location) => (
                        <MantineTable.Tr key={String(location.id)}>
                            <MantineTable.Td>
                                <Text fw={500} component="span">
                                    <Truncate text={locationName(location)} length={40}/>
                                </Text>
                            </MantineTable.Td>
                            <MantineTable.Td>
                                <Truncate text={formatAddress(location.structured_address ?? {})} length={60}/>
                            </MantineTable.Td>
                            <MantineTable.Td>
                                <Text size="sm" c="dimmed" title={location.created_at}>
                                    {location.created_at ? relativeDate(location.created_at) : ''}
                                </Text>
                            </MantineTable.Td>
                            <MantineTable.Td>
                                <ActionMenu location={location}/>
                            </MantineTable.Td>
                        </MantineTable.Tr>
                    ))}
                </MantineTable.Tbody>
            </Table>

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
