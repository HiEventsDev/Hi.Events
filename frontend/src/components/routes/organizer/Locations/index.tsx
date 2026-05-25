import {t} from "@lingui/macro";
import {ActionIcon, Box, Card, Group, Stack, Table, Text} from "@mantine/core";
import {modals} from "@mantine/modals";
import {IconMapPin, IconPencil, IconTrash} from "@tabler/icons-react";
import {useDisclosure} from "@mantine/hooks";
import {useState} from "react";
import {useParams} from "react-router";
import {useGetOrganizerLocations} from "../../../../queries/useGetOrganizerLocations.ts";
import {useDeleteLocation} from "../../../../mutations/useDeleteLocation.ts";
import {IdParam, Location} from "../../../../types.ts";
import {PageBody} from "../../../common/PageBody";
import {PageTitle} from "../../../common/PageTitle";
import {TableSkeleton} from "../../../common/TableSkeleton";
import {formatAddress} from "../../../../utilites/addressUtilities.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {LocationEditModal} from "../../../modals/LocationEditModal";

export default function Locations() {
    const {organizerId} = useParams();
    const locationsQuery = useGetOrganizerLocations(organizerId as IdParam);
    const deleteMutation = useDeleteLocation();
    const [editModalOpen, {open: openEditModal, close: closeEditModal}] = useDisclosure(false);
    const [editTarget, setEditTarget] = useState<Location | null>(null);

    const locations = locationsQuery.data?.data ?? [];

    const handleEdit = (location: Location | null) => {
        setEditTarget(location);
        openEditModal();
    };

    const handleDelete = (location: Location) => {
        modals.openConfirmModal({
            title: t`Delete location`,
            children: (
                <Text size="sm">
                    {t`Delete "${location.name ?? location.structured_address?.venue_name ?? formatAddress(location.structured_address ?? {})}"? Locations referenced by events, occurrences, or set as an organizer's address can't be deleted.`}
                </Text>
            ),
            labels: {confirm: t`Delete`, cancel: t`Cancel`},
            confirmProps: {color: "red"},
            onConfirm: () => {
                deleteMutation.mutate(
                    {organizerId: organizerId as IdParam, locationId: location.id as IdParam},
                    {
                        onSuccess: () => showSuccess(t`Location deleted`),
                        onError: (error: any) =>
                            showError(error?.response?.data?.message ?? t`Could not delete location`),
                    },
                );
            },
        });
    };

    return (
        <PageBody>
            <PageTitle>{t`Locations`}</PageTitle>
            <Box>
                <Card>
                    <Group justify="space-between" mb="md">
                        <Stack gap={2}>
                            <Text fw={600}>{t`Saved Locations`}</Text>
                            <Text size="sm" c="dimmed">
                                {t`Reusable venues for your events. Locations created from the autocomplete are saved here automatically.`}
                            </Text>
                        </Stack>
                        <ActionIcon
                            color="green"
                            size="lg"
                            onClick={() => handleEdit(null)}
                            aria-label={t`Add location`}
                        >
                            <IconMapPin size={18}/>
                        </ActionIcon>
                    </Group>

                    <TableSkeleton isVisible={locationsQuery.isLoading}/>

                    {!locationsQuery.isLoading && locations.length === 0 && (
                        <Text c="dimmed" size="sm">
                            {t`No saved locations yet. They'll appear here as you create events with addresses.`}
                        </Text>
                    )}

                    {!locationsQuery.isLoading && locations.length > 0 && (
                        <Table verticalSpacing="sm">
                            <Table.Thead>
                                <Table.Tr>
                                    <Table.Th>{t`Name`}</Table.Th>
                                    <Table.Th>{t`Address`}</Table.Th>
                                    <Table.Th>{t`Provider`}</Table.Th>
                                    <Table.Th>{t`Actions`}</Table.Th>
                                </Table.Tr>
                            </Table.Thead>
                            <Table.Tbody>
                                {locations.map((loc) => (
                                    <Table.Tr key={String(loc.id)}>
                                        <Table.Td>
                                            <Text fw={500}>
                                                {loc.name ?? loc.structured_address?.venue_name ?? t`Unnamed`}
                                            </Text>
                                        </Table.Td>
                                        <Table.Td>
                                            <Text size="sm" c="dimmed">
                                                {loc.structured_address ? formatAddress(loc.structured_address) : ""}
                                            </Text>
                                        </Table.Td>
                                        <Table.Td>
                                            <Text size="sm" c="dimmed">
                                                {loc.provider ?? t`Manual`}
                                            </Text>
                                        </Table.Td>
                                        <Table.Td>
                                            <Group gap="xs">
                                                <ActionIcon
                                                    variant="subtle"
                                                    onClick={() => handleEdit(loc)}
                                                    aria-label={t`Edit location`}
                                                >
                                                    <IconPencil size={16}/>
                                                </ActionIcon>
                                                <ActionIcon
                                                    variant="subtle"
                                                    color="red"
                                                    onClick={() => handleDelete(loc)}
                                                    aria-label={t`Delete location`}
                                                >
                                                    <IconTrash size={16}/>
                                                </ActionIcon>
                                            </Group>
                                        </Table.Td>
                                    </Table.Tr>
                                ))}
                            </Table.Tbody>
                        </Table>
                    )}
                </Card>
            </Box>

            {editModalOpen && (
                <LocationEditModal
                    onClose={() => {
                        setEditTarget(null);
                        closeEditModal();
                    }}
                    organizerId={organizerId as IdParam}
                    location={editTarget}
                />
            )}
        </PageBody>
    );
}
