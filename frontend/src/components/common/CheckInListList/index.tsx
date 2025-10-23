import {CheckInList, IdParam} from "../../../types";
import {Badge, Button, Progress} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {IconCopy, IconExternalLink, IconHelp, IconPencil, IconPlus, IconTrash, IconUsers} from "@tabler/icons-react";
import Truncate from "../Truncate";
import {NoResultsSplash} from "../NoResultsSplash";
import classes from './CheckInListList.module.scss';
import {Card} from "../Card";
import {Popover} from "../Popover";
import {useState} from "react";
import {ActionMenu} from "../ActionMenu";
import {useDisclosure} from "@mantine/hooks";
import {EditCheckInListModal} from "../../modals/EditCheckInListModal";
import {useDeleteCheckInList} from "../../../mutations/useDeleteCheckInList";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {confirmationDialog} from "../../../utilites/confirmationDialog.tsx";
import {useParams} from "react-router";

interface CheckInListListProps {
    checkInLists: CheckInList[];
    openCreateModal: () => void;
}

export const CheckInListList = ({checkInLists, openCreateModal}: CheckInListListProps) => {
    const [editModalOpen, {open: openEditModal, close: closeEditModal}] = useDisclosure(false);
    const [selectedCheckInListId, setSelectedCheckInListId] = useState<IdParam>();
    const deleteMutation = useDeleteCheckInList();
    const {eventId} = useParams();

    const handleDeleteCheckInList = (checkInListId: IdParam, eventId: IdParam) => {
        deleteMutation.mutate({checkInListId, eventId}, {
            onSuccess: () => {
                showSuccess(t`Check-In List deleted successfully`);
            },
            onError: (error: any) => {
                showError(error.message);
            }
        });
    }

    if (checkInLists.length === 0) {
        return (
            <NoResultsSplash
                heading={t`No Check-In Lists`}
                imageHref={'/blank-slate/check-in-lists.svg'}
                subHeading={(
                    <>
                        <p>
                            <Trans>
                                <p>
                                    Check-in lists help you manage event entry by day, area, or ticket type. You can link tickets to specific lists such as VIP zones or Day 1 passes and share a secure check-in link with staff. No account is required. Check-in works on mobile, desktop, or tablet, using a device camera or HID USB scanner.                                </p>
                            </Trans>
                        </p>
                        <Button
                            loading={deleteMutation.isPending}
                            size={'xs'}
                            leftSection={<IconPlus/>}
                            color={'green'}
                            onClick={() => openCreateModal()}>{t`Create Check-In List`}
                        </Button>
                    </>
                )}
            />
        );
    }

    return (
        <>
            <div className={classes.checkInListList}>
                {checkInLists.map((list) => {
                    const statusMessage = (function () {
                            if (list.is_expired) {
                                return t`This check-in list has expired`;
                            }

                            if (!list.is_active) {
                                return t`This check-in list is not active yet`;
                            }

                            return t`This check-in list is active`;
                        }
                    )();

                    return (
                        <Card className={classes.checkInListCard} key={list.id}>
                            <div className={classes.checkInListHeader}>
                                <div className={classes.checkInListAppliesTo}>
                                    {list.products && (
                                        <Popover
                                            title={list.products.map((product) => (
                                                <div key={product.id}>
                                                    {product.title}
                                                </div>
                                            ))}
                                            position={'bottom'}
                                            withArrow
                                        >
                                            <div className={classes.appliesToText}>
                                                <div>
                                                    {list.products.length > 1 &&
                                                        <Trans>Includes {list.products.length} products</Trans>}
                                                    {list.products.length === 1 && t`Includes 1 product`}
                                                </div>
                                                &nbsp;
                                                <IconHelp size={16}/>
                                            </div>
                                        </Popover>
                                    )}
                                </div>
                                <div className={classes.capacityAssignmentStatus}>
                                    <Popover title={statusMessage} position={'bottom'} withArrow>
                                        <Badge variant={'light'}
                                               color={(!list.is_expired && list.is_active) ? 'green' : 'gray'}>
                                            {!list.is_expired && list.is_active ? t`Active` : t`Inactive`}
                                        </Badge>
                                    </Popover>
                                </div>

                            </div>
                            <div className={classes.checkInListName}>
                                <b>
                                    <Truncate text={list.name} length={30}/>
                                </b>
                            </div>

                            <div className={classes.checkInListInfo}>
                                <div className={classes.checkInListCapacity}>
                                    <Progress
                                        value={checkInLists.length === 0 ? 0 : (list.checked_in_attendees / list.total_attendees) * 100}
                                        radius={'xl'}
                                        color={list.checked_in_attendees === list.total_attendees ? 'primary' : 'green'}
                                        size={'xl'}
                                        style={{marginTop: '10px'}}
                                    />
                                    <div className={classes.capacityText}>
                                        <IconUsers size={18}/> {list.checked_in_attendees} / {list.total_attendees}
                                    </div>
                                </div>
                                <div className={classes.checkInListActions}>
                                    <ActionMenu
                                        itemsGroups={[
                                            {
                                                label: t`Manage`,
                                                items: [
                                                    {
                                                        label: t`Edit Check-In List`,
                                                        icon: <IconPencil size={14}/>,
                                                        onClick: () => {
                                                            setSelectedCheckInListId(list.id as IdParam);
                                                            openEditModal();
                                                        }
                                                    },
                                                    {
                                                        label: t`Copy Check-In URL`,
                                                        icon: <IconCopy size={14}/>,
                                                        onClick: () => {
                                                            navigator.clipboard.writeText(
                                                                `${window.location.origin}/check-in/${list.short_id}`
                                                            ).then(() => {
                                                                showSuccess(t`Check-In URL copied to clipboard`);
                                                            });
                                                        }
                                                    },
                                                    {
                                                        label: t`Open Check-In Page`,
                                                        icon: <IconExternalLink size={14}/>,
                                                        onClick: () => {
                                                            window.open(`/check-in/${list.short_id}`, '_blank');
                                                        }
                                                    }
                                                ],
                                            },
                                            {
                                                label: t`Danger zone`,
                                                items: [
                                                    {
                                                        label: t`Delete Check-In List`,
                                                        icon: <IconTrash size={14}/>,
                                                        onClick: () => {
                                                            confirmationDialog(
                                                                t`Are you sure you would like to delete this Check-In List?`,
                                                                () => {
                                                                    handleDeleteCheckInList(
                                                                        list.id as IdParam,
                                                                        eventId,
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
            {(editModalOpen && selectedCheckInListId)
                && <EditCheckInListModal onClose={closeEditModal}
                                         checkInListId={selectedCheckInListId}/>}
        </>
    );
};
