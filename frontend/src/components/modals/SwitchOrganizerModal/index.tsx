import {useState} from 'react';
import {t} from '@lingui/macro';
import {Avatar, Skeleton, TextInput, UnstyledButton, VisuallyHidden} from '@mantine/core';
import {IconCheck, IconChevronDown, IconChevronRight, IconPlus, IconSearch} from '@tabler/icons-react';
import {useNavigate, useParams} from 'react-router';
import {useAccess} from "../../../hooks/useAccess.ts";
import {useGetOrganizers} from '../../../queries/useGetOrganizers';
import {Organizer, OrganizerStatus} from "../../../types.ts";
import {Modal} from '../../common/Modal';
import classes from './SwitchOrganizerModal.module.scss';

interface SwitchOrganizerModalProps {
    opened: boolean;
    onClose: () => void;
    onCreateOrganizer?: () => void;
    heading?: string;
}

const SEARCHABLE_ORGANIZER_COUNT = 6;

export const SwitchOrganizerModal = ({opened, onClose, onCreateOrganizer, heading}: SwitchOrganizerModalProps) => {
    const navigate = useNavigate();
    const {organizerId: currentOrganizerId} = useParams();
    const {data: organizers, isLoading} = useGetOrganizers();
    const {canManageOrganizer, canCreateOrganizers} = useAccess();
    const [showArchived, setShowArchived] = useState(false);
    const [query, setQuery] = useState('');

    const manageableOrganizers = organizers?.data?.filter(organizer => canManageOrganizer(organizer.id)) || [];
    const normalizedQuery = query.trim().toLowerCase();
    const matchingOrganizers = normalizedQuery
        ? manageableOrganizers.filter(organizer => organizer.name.toLowerCase().includes(normalizedQuery))
        : manageableOrganizers;
    const activeOrganizers = matchingOrganizers.filter(organizer => organizer.status !== OrganizerStatus.ARCHIVED);
    const archivedOrganizers = matchingOrganizers.filter(organizer => organizer.status === OrganizerStatus.ARCHIVED);
    const isArchivedListOpen = showArchived || !!normalizedQuery;

    const selectOrganizer = (organizer: Organizer) => {
        if (String(organizer.id) !== String(currentOrganizerId)) {
            navigate(`/manage/organizer/${organizer.id}/dashboard`);
        }
        onClose();
    };

    const renderOrganizer = (organizer: Organizer) => {
        const isCurrent = String(organizer.id) === String(currentOrganizerId);
        const isArchived = organizer.status === OrganizerStatus.ARCHIVED;

        return (
            <UnstyledButton
                key={organizer.id}
                className={classes.row}
                data-archived={isArchived || undefined}
                aria-current={isCurrent || undefined}
                onClick={() => selectOrganizer(organizer)}
            >
                <Avatar
                    src={organizer.images?.find(image => image.type === 'ORGANIZER_LOGO')?.url}
                    alt=""
                    size={32}
                    radius={8}
                    color="primary"
                >
                    {organizer.name.charAt(0).toUpperCase()}
                </Avatar>
                <span className={classes.name}>{organizer.name}</span>
                {isArchived && <span className={classes.archivedLabel}>{t`Archived`}</span>}
                {isCurrent && (
                    <>
                        <IconCheck className={classes.currentIcon} size={18} stroke={2} aria-hidden/>
                        <VisuallyHidden>{t`Current organizer`}</VisuallyHidden>
                    </>
                )}
            </UnstyledButton>
        );
    };

    return (
        <Modal opened={opened} onClose={onClose} heading={heading || t`Switch Organizer`} size={440}>
            {manageableOrganizers.length >= SEARCHABLE_ORGANIZER_COUNT && (
                <div className={classes.search}>
                    <TextInput
                        data-autofocus
                        aria-label={t`Search organizers`}
                        placeholder={t`Search...`}
                        leftSection={<IconSearch size={16} stroke={1.5}/>}
                        value={query}
                        onChange={event => setQuery(event.currentTarget.value)}
                        onKeyDown={event => {
                            const firstMatch = activeOrganizers[0] ?? archivedOrganizers[0];
                            if (event.key === 'Enter' && firstMatch) {
                                selectOrganizer(firstMatch);
                            }
                        }}
                    />
                </div>
            )}

            <div className={classes.list}>
                {isLoading && Array.from({length: 3}, (_, index) => (
                    <Skeleton key={index} height={36} radius={8} my={6}/>
                ))}

                {!isLoading && matchingOrganizers.length === 0 && (
                    <p className={classes.empty}>
                        {normalizedQuery ? t`No search results.` : t`No other organizers available`}
                    </p>
                )}

                {activeOrganizers.map(renderOrganizer)}

                {archivedOrganizers.length > 0 && (
                    <>
                        <UnstyledButton
                            className={classes.archivedToggle}
                            aria-expanded={isArchivedListOpen}
                            onClick={() => setShowArchived(!showArchived)}
                        >
                            {isArchivedListOpen ? <IconChevronDown size={14}/> : <IconChevronRight size={14}/>}
                            {t`Archived Organizers`}
                            <span className={classes.archivedCount}>{archivedOrganizers.length}</span>
                        </UnstyledButton>
                        {isArchivedListOpen && archivedOrganizers.map(renderOrganizer)}
                    </>
                )}
            </div>

            {canCreateOrganizers && (
                <div className={classes.footer}>
                    <UnstyledButton
                        className={classes.row}
                        onClick={() => {
                            onClose();
                            onCreateOrganizer?.();
                        }}
                    >
                        <span className={classes.createIcon}>
                            <IconPlus size={16} stroke={1.8}/>
                        </span>
                        <span className={classes.createLabel}>{t`Create Organizer`}</span>
                    </UnstyledButton>
                </div>
            )}
        </Modal>
    );
};
