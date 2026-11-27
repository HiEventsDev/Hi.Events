import {Link} from "react-router";
import {t} from "@lingui/macro";
import {ActionIcon, Button, Menu} from "@mantine/core";
import {IconChartPie, IconChevronDown, IconDotsVertical, IconDownload, IconListSearch, IconPlus, IconRefresh, IconTags, IconUpload} from "@tabler/icons-react";
import {CreateButton} from "../../../../../components/common/CreateButton";
import {IdParam} from "../../../../../types.ts";
import {useResyncAudience} from "../../../hooks/useResyncAudience.ts";
import {ContactsModal} from "./ContactsModals.tsx";

interface ContactsHeaderActionsProps {
    organizerId: IdParam;
    canChange: boolean;
    canRemove: boolean;
    isExporting: boolean;
    onExport: () => void;
    onOpenModal: (modal: ContactsModal) => void;
}

export const ContactsHeaderActions = ({organizerId, canChange, canRemove, isExporting, onExport, onOpenModal}: ContactsHeaderActionsProps) => {
    const {resync, isBusy} = useResyncAudience(organizerId);

    return (
        <>
            {canChange && (
                <Menu position="bottom-end" shadow="md" withinPortal>
                    <Menu.Target>
                        <Button
                            variant="default"
                            leftSection={<IconUpload size={16}/>}
                            rightSection={<IconChevronDown size={14}/>}
                            data-testid="audience-contact-import-button"
                        >
                            {t`Import`}
                        </Button>
                    </Menu.Target>
                    <Menu.Dropdown>
                        <Menu.Item onClick={() => onOpenModal('import')} data-testid="audience-import-contacts">
                            {t`Contacts`}
                        </Menu.Item>
                        <Menu.Item onClick={() => onOpenModal('suppressions')} data-testid="audience-import-suppressions-button">
                            {t`Suppression list`}
                        </Menu.Item>
                    </Menu.Dropdown>
                </Menu>
            )}
            <Menu position="bottom-end" shadow="md" withinPortal>
                <Menu.Target>
                    <ActionIcon variant="default" size={36} aria-label={t`More actions`} data-testid="audience-contacts-more">
                        <IconDotsVertical size={18}/>
                    </ActionIcon>
                </Menu.Target>
                <Menu.Dropdown>
                    <Menu.Item
                        leftSection={<IconDownload size={14}/>}
                        onClick={onExport}
                        disabled={isExporting}
                    >
                        {t`Export this list`}
                    </Menu.Item>
                    <Menu.Item
                        leftSection={<IconListSearch size={14}/>}
                        onClick={() => onOpenModal('check')}
                        data-testid="audience-check-addresses-button"
                    >
                        {t`Check if an address is suppressed`}
                    </Menu.Item>
                    {canRemove && (
                        <Menu.Item leftSection={<IconTags size={14}/>} onClick={() => onOpenModal('tags')}>
                            {t`Manage tags`}
                        </Menu.Item>
                    )}
                    <Menu.Item
                        leftSection={<IconChartPie size={14}/>}
                        component={Link}
                        to={`/manage/organizer/${organizerId}/audience/segments`}
                    >
                        {t`Manage segments`}
                    </Menu.Item>
                    {canChange && (
                        <>
                            <Menu.Divider/>
                            <Menu.Item
                                leftSection={<IconRefresh size={14}/>}
                                onClick={resync}
                                disabled={isBusy}
                                data-testid="audience-resync-button"
                            >
                                {t`Refresh audience`}
                            </Menu.Item>
                        </>
                    )}
                </Menu.Dropdown>
            </Menu>
            {canChange && (
                <CreateButton color="green" rightSection={<IconPlus/>} onClick={() => onOpenModal('add')}>
                    {t`Add contact`}
                </CreateButton>
            )}
        </>
    );
};
