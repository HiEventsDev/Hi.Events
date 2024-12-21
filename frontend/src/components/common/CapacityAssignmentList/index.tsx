import {CapacityAssignment, IdParam} from "../../../types";
import {Badge, Button, Progress} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {IconHelp, IconPencil, IconPlus, IconTrash} from "@tabler/icons-react";
import Truncate from "../Truncate";
import {NoResultsSplash} from "../NoResultsSplash";
import classes from './CapacityAssignmentList.module.scss';
import {Card} from "../Card";
import {Popover} from "../Popover";
import {useState} from "react";
import {ActionMenu} from "../ActionMenu";
import {useDisclosure} from "@mantine/hooks";
import {EditCapacityAssignmentModal} from "../../modals/EditCapacityAssignmentModal";
import {useDeleteCapacityAssignment} from "../../../mutations/useDeleteCapacityAssignment";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {confirmationDialog} from "../../../utilites/confirmationDialog.tsx";

interface CapacityAssignmentListProps {
    capacityAssignments: CapacityAssignment[];
    openCreateModal: () => void;
}

export const CapacityAssignmentList = ({capacityAssignments, openCreateModal}: CapacityAssignmentListProps) => {
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
                                    Capacity assignments let you manage capacity across tickets or an entire event. Ideal
                                    for multi-day events, workshops, and more, where controlling attendance is crucial.
                                </p>
                                <p>
                                    For instance, you can associate a capacity assignment with <b>Day One</b> and <b>All
                                    Days</b> ticket. Once the capacity is reached, both tickets will automatically stop
                                    being available for sale.
                                </p>
                            </Trans>
                        </p>
                        <Button
                            size={'xs'}
                            leftSection={<IconPlus/>}
                            color={'green'}
                            onClick={() => openCreateModal()}>{t`Create Capacity Assignment`}
                        </Button>
                    </>
                )}
            />
        );
    }

    return (
        <>
            <div className={classes.capacityAssignmentList}>
                {capacityAssignments.map((assignment) => {
                    const capacityPercentage = assignment.capacity
                        ? (assignment.used_capacity / assignment.capacity) * 100
                        : 0;
                    const capacityColor = capacityPercentage > 80 ? 'red' : capacityPercentage > 50 ? 'yellow' : 'green';

                    return (
                        <Card className={classes.capacityCard} key={assignment.id}>
                            <div className={classes.capacityAssignmentHeader}>
                                <div className={classes.capacityAssignmentAppliesTo}>
                                    {assignment.products && (
                                        <Popover
                                            title={assignment.products.map((product) => (
                                                <div key={product.id}>
                                                    {product.title}
                                                </div>
                                            ))}
                                            position={'bottom'}
                                            withArrow
                                        >
                                            <div className={classes.appliesToText}>
                                                <div>
                                                    {assignment.products.length > 1 &&
                                                        <Trans>Applies to {assignment.products.length} products</Trans>}
                                                    {assignment.products.length === 1 && t`Applies to 1 product`}
                                                </div>
                                                &nbsp;
                                                <IconHelp size={16}/>
                                            </div>
                                        </Popover>
                                    )}
                                </div>

                                <div className={classes.capacityAssignmentStatus}>
                                    <Badge variant={'light'} color={assignment.status === 'ACTIVE' ? 'green' : 'gray'}>
                                        {assignment.status}
                                    </Badge>
                                </div>
                            </div>
                            <div className={classes.capacityAssignmentName}>
                                <b>
                                    <Truncate text={assignment.name} length={30}/>
                                </b>
                            </div>

                            <div className={classes.capacityAssignmentInfo}>
                                <div className={classes.capacityAssignmentCapacity}>
                                    {assignment.capacity ? (
                                        <div className={classes.capacity}>
                                            <Progress
                                                className={classes.capacityBar}
                                                value={capacityPercentage}
                                                color={capacityColor}
                                                size={'md'}
                                            />
                                            <div className={classes.capacityText}>
                                                {assignment.used_capacity}/{assignment.capacity}
                                            </div>
                                        </div>
                                    ) : (
                                        <div className={classes.capacityText}>
                                            {assignment.used_capacity}/∞
                                        </div>
                                    )}
                                </div>
                                <div className={classes.capacityAssignmentActions}>
                                    <ActionMenu
                                        itemsGroups={[
                                            {
                                                label: t`Manage`,
                                                items: [
                                                    {
                                                        label: t`Edit Capacity`,
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
                                                        icon: <IconTrash size={14}/>,
                                                        onClick: () => {
                                                            confirmationDialog(
                                                                t`Are you sure you would like to delete this Capacity Assignment?`,
                                                                () => {
                                                                    handleDeleteProduct(
                                                                        assignment.id as IdParam,
                                                                        assignment.event_id as IdParam,
                                                                    );
                                                                })
                                                        },
                                                        color: 'red',
                                                    },
                                                ],
                                            },
                                        ]}
                                    />
                                </div>
                            </div>
                        </Card>
                    );
                })}
            </div>
            {(editModalOpen && selectedCapacityAssignmentId)
                && <EditCapacityAssignmentModal onClose={closeEditModal}
                                                capacityAssignmentId={selectedCapacityAssignmentId}/>}
        </>
    );
};
