import React from 'react';
import {Modal} from '../../common/Modal';
import {useGetOrganizers} from '../../../queries/useGetOrganizers';
import {IconArrowsHorizontal, IconBuilding} from '@tabler/icons-react';
import {t, Trans} from '@lingui/macro';
import {useNavigate, useParams} from 'react-router';
import classes from './SwitchOrganizerModal.module.scss';
import {LoadingMask} from '../../common/LoadingMask';
import {IdParam} from "../../../types.ts";

interface SwitchOrganizerModalProps {
    opened: boolean;
    onClose: () => void;
}

export const SwitchOrganizerModal: React.FC<SwitchOrganizerModalProps> = ({opened, onClose}) => {
    const navigate = useNavigate();
    const {organizerId: currentOrganizerId} = useParams();
    const {data: organizers, isLoading} = useGetOrganizers();

    const handleOrganizerSelect = (organizerId: IdParam) => {
        navigate(`/manage/organizer/${organizerId}/dashboard`);
        onClose();
    };

    // Filter out the current organizer
    const availableOrganizers = organizers?.data?.filter(org => String(org.id) !== String(currentOrganizerId)) || [];

    return (
        <Modal
            opened={opened}
            onClose={onClose}
            heading={t`Switch Organizer`}
            modalHeader="branded"
            size="md"
        >
            {isLoading ? (
                <LoadingMask/>
            ) : (
                <div className={classes.organizerList}>
                    {availableOrganizers.length === 0 ? (
                        <div className={classes.emptyState}>
                            <IconBuilding size={48} stroke={1.5}/>
                            <p><Trans>No other organizers available</Trans></p>
                        </div>
                    ) : (
                        availableOrganizers.map((organizer) => (
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
                        ))
                    )}
                </div>
            )}
        </Modal>
    );
};
