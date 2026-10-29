import {Avatar, Button, Center, Loader} from "@mantine/core";
import {useDisclosure} from "@mantine/hooks";
import {t} from "@lingui/macro";
import {IconChevronRight, IconPlus} from "@tabler/icons-react";
import {NavLink} from "react-router";
import {useGetOrganizers} from "../../../../queries/useGetOrganizers.ts";
import {useAccess} from "../../../../hooks/useAccess.ts";
import {Organizer, OrganizerStatus} from "../../../../types.ts";
import {CreateOrganizerModal} from "../../../modals/CreateOrganizerModal";
import {SettingsPage, SettingsSection} from "../../../common/SettingsPage";
import {StatusDot} from "../../../common/StatusDot";
import classes from "./Organizers.module.scss";

const logoUrl = (organizer: Organizer) => organizer.images?.find(image => image.type === 'ORGANIZER_LOGO')?.url;

const OrganizerStatusLabel = ({status}: { status?: OrganizerStatus }) => {
    switch (status) {
        case OrganizerStatus.LIVE:
            return <StatusDot tone="success">{t`Live`}</StatusDot>;
        case OrganizerStatus.ARCHIVED:
            return <StatusDot tone="muted">{t`Archived`}</StatusDot>;
        default:
            return <StatusDot tone="muted">{t`Draft`}</StatusDot>;
    }
};

const Organizers = () => {
    const {data: organizersResponse, isFetched, isError, isFetching, refetch} = useGetOrganizers();
    const {canCreateOrganizers, canManageOrganizer, isReadOnly} = useAccess();
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);

    const organizers = (organizersResponse?.data ?? [])
        .filter(organizer => canManageOrganizer(organizer.id))
        .sort((a, b) => a.name.localeCompare(b.name));

    const renderContent = () => {
        if (isError && !organizersResponse) {
            return (
                <div className={classes.empty}>
                    <p className={classes.emptyText}>{t`Couldn't load your organizers`}</p>
                    <Button variant="default" size="xs" loading={isFetching} onClick={() => refetch()}>{t`Try again`}</Button>
                </div>
            );
        }

        if (!isFetched) {
            return <Center py="xl"><Loader size="sm"/></Center>;
        }

        if (organizers.length === 0) {
            return <p className={classes.empty}>{t`No organizers yet.`}</p>;
        }

        return organizers.map(organizer => (
            <NavLink
                key={organizer.id}
                to={`/manage/organizer/${organizer.id}/${isReadOnly ? 'dashboard' : 'settings'}`}
                className={classes.organizer}
                data-testid="settings-organizer-row"
            >
                <Avatar src={logoUrl(organizer)} name={organizer.name} color="initials" radius="md" size={40}/>
                <div className={classes.details}>
                    <div className={classes.nameLine}>
                        <span className={classes.name}>{organizer.name}</span>
                        <span className={classes.status}><OrganizerStatusLabel status={organizer.status}/></span>
                    </div>
                    <div className={classes.meta}>
                        {[organizer.email, organizer.currency, organizer.timezone].filter(Boolean).join(' · ')}
                    </div>
                </div>
                <span className={classes.open}>
                    <span className={classes.openLabel}>{isReadOnly ? t`Open` : t`Open settings`}</span>
                    <IconChevronRight size={16}/>
                </span>
            </NavLink>
        ));
    };

    return (
        <SettingsPage
            title={t`Organizers`}
            description={t`Each organizer has its own public page, branding, payouts and event defaults.`}
            actions={canCreateOrganizers && (
                <Button leftSection={<IconPlus size={16}/>} onClick={openCreateModal} data-testid="settings-create-organizer-button">
                    {t`Create organizer`}
                </Button>
            )}
        >
            <SettingsSection>{renderContent()}</SettingsSection>

            {createModalOpen && <CreateOrganizerModal onClose={closeCreateModal}/>}
        </SettingsPage>
    );
};

export default Organizers;
