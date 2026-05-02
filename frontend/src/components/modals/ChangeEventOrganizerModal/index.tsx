import {Modal} from '../../common/Modal';
import {useGetOrganizers} from '../../../queries/useGetOrganizers';
import {IconArrowsExchange, IconBuilding} from '@tabler/icons-react';
import {t, Trans} from '@lingui/macro';
import classes from './ChangeEventOrganizerModal.module.scss';
import {LoadingMask} from '../../common/LoadingMask';
import {Event, IdParam, OrganizerStatus} from '../../../types.ts';
import {useUpdateEvent} from '../../../mutations/useUpdateEvent.ts';
import {showError, showSuccess} from '../../../utilites/notifications.tsx';

interface ChangeEventOrganizerModalProps {
    opened: boolean;
    onClose: () => void;
    event: Event;
}

export const ChangeEventOrganizerModal: React.FC<ChangeEventOrganizerModalProps> = ({
    opened,
    onClose,
    event,
}) => {
    const {data: organizers, isLoading} = useGetOrganizers();
    const updateMutation = useUpdateEvent();

    const currentOrganizerId = event.organizer_id;
    const currentOrganizerName = event.organizer?.name
        ?? organizers?.data?.find(o => String(o.id) === String(currentOrganizerId))?.name;

    const selectableOrganizers = (organizers?.data ?? [])
        .filter(org => org.status !== OrganizerStatus.ARCHIVED)
        .filter(org => String(org.id) !== String(currentOrganizerId));

    const handleSelect = (organizerId: IdParam) => {
        updateMutation.mutate(
            {
                eventId: event.id,
                eventData: {
                    ...event,
                    organizer_id: organizerId,
                },
            },
            {
                onSuccess: () => {
                    showSuccess(t`Event moved to new organizer`);
                    onClose();
                },
                onError: (error: any) => {
                    showError(
                        error?.response?.data?.errors?.organizer_id?.[0]
                        ?? error?.response?.data?.message
                        ?? t`Failed to change organizer. Please try again.`
                    );
                },
            },
        );
    };

    return (
        <Modal
            opened={opened}
            onClose={onClose}
            heading={t`Change Organizer`}
            modalHeader="branded"
            size="md"
        >
            {isLoading ? (
                <LoadingMask/>
            ) : (
                <div className={classes.organizerList}>
                    {currentOrganizerName && (
                        <div className={classes.currentOrganizer}>
                            <div className={classes.currentLabel}>
                                <Trans>Current organizer</Trans>
                            </div>
                            <div className={classes.currentName}>{currentOrganizerName}</div>
                        </div>
                    )}

                    {selectableOrganizers.length === 0 ? (
                        <div className={classes.emptyState}>
                            <IconBuilding size={48} stroke={1.5}/>
                            <p><Trans>No other active organizers in this account</Trans></p>
                        </div>
                    ) : (
                        selectableOrganizers.map((organizer) => (
                            <button
                                key={organizer.id}
                                className={classes.organizerItem}
                                onClick={() => handleSelect(organizer.id)}
                                disabled={updateMutation.isPending}
                            >
                                <div className={classes.organizerLogo}>
                                    {organizer.images?.find((image) => image.type === 'ORGANIZER_LOGO') ? (
                                        <img
                                            src={organizer.images.find((image) => image.type === 'ORGANIZER_LOGO')?.url}
                                            alt={organizer.name}
                                        />
                                    ) : (
                                        <div className={classes.logoPlaceholder}>
                                            <IconBuilding size={20} stroke={1.5}/>
                                        </div>
                                    )}
                                </div>
                                <div className={classes.organizerInfo}>
                                    <h4 className={classes.organizerName}>{organizer.name}</h4>
                                </div>
                                <IconArrowsExchange size={20} className={classes.selectIcon}/>
                            </button>
                        ))
                    )}
                </div>
            )}
        </Modal>
    );
};
