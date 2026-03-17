import {useState} from 'react';
import {Modal} from '../../common/Modal';
import {useGetOrganizers} from '../../../queries/useGetOrganizers';
import {IconArrowsHorizontal, IconBuilding, IconArchive, IconChevronDown, IconChevronRight, IconPlus} from '@tabler/icons-react';
import {t, Trans} from '@lingui/macro';
import {useNavigate, useParams} from 'react-router';
import classes from './SwitchOrganizerModal.module.scss';
import {LoadingMask} from '../../common/LoadingMask';
import {IdParam, OrganizerStatus} from "../../../types.ts";

interface SwitchOrganizerModalProps {
    opened: boolean;
    onClose: () => void;
    onCreateOrganizer?: () => void;
    heading?: string;
    excludeCurrentOrganizer?: boolean;
}

export const SwitchOrganizerModal: React.FC<SwitchOrganizerModalProps> = ({
    opened,
    onClose,
    onCreateOrganizer,
    heading,
    excludeCurrentOrganizer = true,
}) => {
    const navigate = useNavigate();
    const {organizerId: currentOrganizerId} = useParams();
    const {data: organizers, isLoading} = useGetOrganizers();
    const [showArchived, setShowArchived] = useState(false);

    const handleOrganizerSelect = (organizerId: IdParam) => {
        navigate(`/manage/organizer/${organizerId}/dashboard`);
        onClose();
    };

    const allOrganizers = excludeCurrentOrganizer
        ? organizers?.data?.filter(org => String(org.id) !== String(currentOrganizerId)) || []
        : organizers?.data || [];
    const activeOrganizers = allOrganizers.filter(org => org.status !== OrganizerStatus.ARCHIVED);
    const archivedOrganizers = allOrganizers.filter(org => org.status === OrganizerStatus.ARCHIVED);

    return (
        <Modal
            opened={opened}
            onClose={onClose}
            heading={heading || t`Switch Organizer`}
            modalHeader="branded"
            size="md"
        >
                {isLoading ? (
                    <LoadingMask/>
                ) : (
                    <div className={classes.organizerList}>
                        {activeOrganizers.length === 0 && archivedOrganizers.length === 0 ? (
                            <div className={classes.emptyState}>
                                <IconBuilding size={48} stroke={1.5}/>
                                <p><Trans>No other organizers available</Trans></p>
                            </div>
                        ) : (
                            <>
                                {activeOrganizers.map((organizer) => (
                                    <button
                                        key={organizer.id}
                                        className={classes.organizerItem}
                                        onClick={() => handleOrganizerSelect(organizer.id)}
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
                                        <IconArrowsHorizontal size={20} className={classes.selectIcon}/>
                                    </button>
                                ))}
                            </>
                        )}

                        <button
                            className={classes.createButton}
                            onClick={() => {
                                onClose();
                                onCreateOrganizer?.();
                            }}
                        >
                            <IconPlus size={18}/>
                            <Trans>Create Organizer</Trans>
                        </button>

                        {archivedOrganizers.length > 0 && (
                            <>
                                <button
                                    className={classes.archivedToggle}
                                    onClick={() => setShowArchived(!showArchived)}
                                >
                                    {showArchived ? <IconChevronDown size={16}/> : <IconChevronRight size={16}/>}
                                    <Trans>Archived Organizers</Trans>
                                    <span className={classes.archivedCount}>({archivedOrganizers.length})</span>
                                </button>
                                {showArchived && archivedOrganizers.map((organizer) => (
                                    <button
                                        key={organizer.id}
                                        className={classes.archivedItem}
                                        onClick={() => handleOrganizerSelect(organizer.id)}
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
                                        <span className={classes.archiveBadge}>
                                            <IconArchive size={14}/>
                                        </span>
                                    </button>
                                ))}
                            </>
                        )}
                    </div>
                )}
        </Modal>
    );
};
