import {CapacityAssignment, IdParam} from "../../../types";
import {CreateButton} from "../CreateButton";
import {Progress, Tooltip, VisuallyHidden} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {IconPencil, IconPlus, IconTrash} from "@tabler/icons-react";
import {NoResultsSplash} from "../NoResultsSplash";
import classes from './CapacityAssignmentList.module.scss';
import {useMemo, useState} from "react";
import {ActionMenu, RowActionsTarget} from "../ActionMenu";
import {TanStackTable, TanStackTableColumn} from "../TanStackTable";
import {CellText, IdentityCell} from "../TanStackTable/cells.tsx";
import {sortableHeader, TableSort} from "../TanStackTable/sorting.tsx";
import {StatusBadge} from "../StatusBadge";
import {useDisclosure} from "@mantine/hooks";
import {EditCapacityAssignmentModal} from "../../modals/EditCapacityAssignmentModal";
import {useDeleteCapacityAssignment} from "../../../mutations/useDeleteCapacityAssignment";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {confirmationDialog} from "../../../utilites/confirmationDialog.tsx";

interface CapacityAssignmentListProps {
    capacityAssignments: CapacityAssignment[];
    openCreateModal: () => void;
    isRefreshing?: boolean;
    sort?: TableSort;
}

export const CapacityAssignmentList = ({capacityAssignments, openCreateModal, isRefreshing, sort}: CapacityAssignmentListProps) => {
    const [editModalOpen, {open: openEditModal, close: closeEditModal}] = useDisclosure(false);
    const [selectedCapacityAssignmentId, setSelectedCapacityAssignmentId] = useState<IdParam>();
    const deleteMutation = useDeleteCapacityAssignment();

    const handleDeleteProduct = (capacityAssignmentId: IdParam, eventId: IdParam) => {
        deleteMutation.mutate({capacityAssignmentId, eventId}, {
            onSuccess: () => {
                showSuccess(t`Capacity Assignment deleted successfully`);
            },
            onError: (error: any) => {
                showError(error.message);
            }
        });
    }

    const columns = useMemo<TanStackTableColumn<CapacityAssignment>[]>(() => [
        {
            id: 'name',
            enableHiding: false,
            ...sortableHeader(t`Name`, 'name', sort, 'asc'),
            meta: {label: t`Name`, sticky: 'left'},
            cell: ({row: {original: assignment}}) => {
                const products = assignment.products ?? [];

                return (
                    <IdentityCell
                        stacked
                        primary={<CellText strong title={assignment.name}>{assignment.name}</CellText>}
                        secondary={products.length > 0 && (
                            <Tooltip
                                label={products.map(product => product.title).join('\n')}
                                multiline
                                withArrow
                                style={{whiteSpace: 'pre-line'}}
                            >
                                <span>
                                    {products.length === 1
                                        ? t`Applies to 1 product`
                                        : <Trans>Applies to {products.length} products</Trans>}
                                </span>
                            </Tooltip>
                        )}
                    />
                );
            },
        },
        {
            id: 'usage',
            ...sortableHeader(t`Capacity`, 'used_capacity', sort),
            meta: {label: t`Capacity`},
            cell: ({row: {original: assignment}}) => {
                const percentage = assignment.capacity ? (assignment.used_capacity / assignment.capacity) * 100 : 0;
                const color = percentage > 80 ? 'red' : percentage > 50 ? 'yellow' : 'green';

                return (
                    <span className={classes.usage}>
                        {!!assignment.capacity && (
                            <Progress className={classes.usageBar} value={percentage} color={color} size="sm" radius="xl"/>
                        )}
                        <span>{assignment.used_capacity}/{assignment.capacity || '∞'}</span>
                    </span>
                );
            },
        },
        {
            id: 'status',
            header: t`Status`,
            cell: ({row: {original: assignment}}) => (
                <StatusBadge tone={assignment.status === 'ACTIVE' ? 'positive' : 'neutral'}>
                    {assignment.status === 'ACTIVE' ? t`Active` : t`Inactive`}
                </StatusBadge>
            ),
        },
        {
            id: 'actions',
            header: () => <VisuallyHidden>{t`Actions`}</VisuallyHidden>,
            enableHiding: false,
            meta: {sticky: 'right'},
            cell: ({row: {original: assignment}}) => (
                <ActionMenu
                    target={<RowActionsTarget/>}
                    itemsGroups={[
                        {
                            label: t`Manage`,
                            items: [
                                {
                                    label: t`Edit Capacity`,
                                    requiresEdit: true,
                                    icon: <IconPencil size={14}/>,
                                    onClick: () => {
                                        setSelectedCapacityAssignmentId(assignment.id as IdParam);
                                        openEditModal();
                                    }
                                },
                            ],
                        },
                        {
                            label: t`Danger zone`,
                            items: [
                                {
                                    label: t`Delete Capacity`,
                                    requiresEdit: true,
                                    icon: <IconTrash size={14}/>,
                                    onClick: () => {
                                        confirmationDialog(
                                            t`Are you sure you would like to delete this Capacity Assignment?`,
                                            () => handleDeleteProduct(assignment.id as IdParam, assignment.event_id as IdParam),
                                        )
                                    },
                                    color: 'red',
                                },
                            ],
                        },
                    ]}
                />
            ),
        },
    ], [sort?.sortBy, sort?.sortDirection]);

    if (capacityAssignments.length === 0) {
        return (
            <NoResultsSplash
                heading={t`No Capacity Assignments`}
                imageHref={'/blank-slate/capacity-assignments.svg'}
                subHeading={(
                    <>
                        <p>
                            <Trans>
                                <p>
                                    Set a single total attendance limit that applies to multiple ticket types at once.
                                </p>
                                <p>
                                    For example, if you link a <b>Day Pass</b> and a <b>Full Weekend</b> ticket, they will both draw from the same pool of spots. Once the limit is reached, all linked tickets automatically stop selling.
                                </p>
                            </Trans>
                        </p>
                        <CreateButton
                            size={'xs'}
                            leftSection={<IconPlus/>}
                            color={'green'}
                            onClick={() => openCreateModal()}>{t`Create Capacity Assignment`}
                        </CreateButton>
                    </>
                )}
            />
        );
    }

    return (
        <>
            <TanStackTable rowLines={2} data={capacityAssignments} columns={columns} noCard isRefreshing={isRefreshing}/>
            {(editModalOpen && selectedCapacityAssignmentId)
                && <EditCapacityAssignmentModal onClose={closeEditModal}
                                                capacityAssignmentId={selectedCapacityAssignmentId}/>}
        </>
    );
};
