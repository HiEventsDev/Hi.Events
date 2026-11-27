import {Link} from "react-router";
import {t} from "@lingui/macro";
import {ActionIcon, Button, Menu} from "@mantine/core";
import {useMediaQuery} from "@mantine/hooks";
import {IconArrowLeft, IconChevronDown, IconClock, IconCopy, IconDots, IconSend, IconTemplate, IconTestPipe, IconTrash} from "@tabler/icons-react";
import {formatDateWithLocale} from "../../../../../utilites/dates.ts";
import {CrmCampaign} from "../../../types.ts";
import {isCampaignStopped} from "../../../utilites/labels.ts";
import {CampaignStatusBadge} from "../../CampaignStatusBadge";
import classes from "./Campaign.module.scss";

interface CampaignActionBarProps {
    basePath: string;
    campaign: CrmCampaign | undefined;
    isNew: boolean;
    statusDate: string | null | undefined;
    timezone: string;
    canCopy: boolean;
    canDelete: boolean;
    canStop: boolean;
    isEditable: boolean;
    isSaving: boolean;
    isTesting: boolean;
    isStopping: boolean;
    onDuplicate: () => void;
    onSaveTemplate?: () => void;
    testLabel: string;
    onDelete: () => void;
    onTest: () => void;
    onSave: () => void;
    onSend: (when: 'now' | 'later') => void;
    onStop: () => void;
    stopLabel: string;
}

export const CampaignActionBar = ({
    basePath,
    campaign,
    isNew,
    statusDate,
    timezone,
    canCopy,
    canDelete,
    canStop,
    isEditable,
    isSaving,
    isTesting,
    isStopping,
    onDuplicate,
    onSaveTemplate,
    testLabel,
    onDelete,
    onTest,
    onSave,
    onSend,
    onStop,
    stopLabel,
}: CampaignActionBarProps) => {
    const isBusy = isSaving || isTesting;
    const isNarrow = useMediaQuery('(max-width: 48em)');
    const showsTestInMenu = isEditable && !!isNarrow;
    const showsDeleteInMenu = canDelete && !!isNarrow;

    return (
        <div className={classes.bar}>
            <Link to={basePath} className={classes.back}><IconArrowLeft size={14}/>{t`Campaigns`}</Link>
            {(isNew || campaign) && (
                <span className={classes.barStatus} data-testid="campaign-status">
                    <CampaignStatusBadge status={campaign?.status ?? 'DRAFT'} isStopped={!!campaign && isCampaignStopped(campaign)}/>
                    {statusDate && <span>{formatDateWithLocale(statusDate, 'shortDateTime', timezone)}</span>}
                </span>
            )}
            {(isEditable || canDelete || canStop || canCopy) && (
                <div className={classes.barActions}>
                    {(canCopy || showsDeleteInMenu || showsTestInMenu) && (
                        <Menu position="bottom-end" withinPortal>
                            <Menu.Target>
                                <ActionIcon variant="default" size="lg" aria-label={t`More actions`} data-testid="campaign-more-actions">
                                    <IconDots size={16}/>
                                </ActionIcon>
                            </Menu.Target>
                            <Menu.Dropdown>
                                {showsTestInMenu && (
                                    <Menu.Item leftSection={<IconTestPipe size={14}/>} onClick={onTest} disabled={isBusy}>
                                        {testLabel}
                                    </Menu.Item>
                                )}
                                {canCopy && (
                                    <Menu.Item leftSection={<IconCopy size={14}/>} onClick={onDuplicate} data-testid="campaign-duplicate">
                                        {t`Duplicate`}
                                    </Menu.Item>
                                )}
                                {canCopy && onSaveTemplate && (
                                    <Menu.Item leftSection={<IconTemplate size={14}/>} onClick={onSaveTemplate} data-testid="campaign-save-template">
                                        {t`Save as template`}
                                    </Menu.Item>
                                )}
                                {showsDeleteInMenu && (
                                    <Menu.Item color="red" leftSection={<IconTrash size={14}/>} onClick={onDelete}>
                                        {t`Delete`}
                                    </Menu.Item>
                                )}
                            </Menu.Dropdown>
                        </Menu>
                    )}
                    {canDelete && !isNarrow && (
                        <Button variant="subtle" color="red" size="sm" onClick={onDelete} disabled={isBusy}>
                            {t`Delete`}
                        </Button>
                    )}
                    {isEditable && (
                        <>
                            {!isNarrow && (
                                <Button
                                    variant="default"
                                    size="sm"
                                    leftSection={<IconTestPipe size={16}/>}
                                    onClick={onTest}
                                    loading={isTesting}
                                    disabled={isSaving}
                                >
                                    {testLabel}
                                </Button>
                            )}
                            <Button
                                variant="default"
                                size="sm"
                                onClick={onSave}
                                loading={isSaving}
                                disabled={isTesting}
                            >
                                {t`Save draft`}
                            </Button>
                            <Button.Group>
                                <Button
                                    size="sm"
                                    leftSection={<IconSend size={16}/>}
                                    onClick={() => onSend('now')}
                                    disabled={isBusy}
                                    data-testid="campaign-review-send"
                                >
                                    {t`Send`}
                                </Button>
                                <Menu position="bottom-end" withinPortal>
                                    <Menu.Target>
                                        <Button size="sm" px={8} aria-label={t`More send options`} disabled={isBusy}>
                                            <IconChevronDown size={16}/>
                                        </Button>
                                    </Menu.Target>
                                    <Menu.Dropdown>
                                        <Menu.Item leftSection={<IconClock size={14}/>} onClick={() => onSend('later')}>
                                            {t`Schedule for later`}
                                        </Menu.Item>
                                    </Menu.Dropdown>
                                </Menu>
                            </Button.Group>
                        </>
                    )}
                    {canStop && campaign && (
                        <Button variant="default" size="sm" onClick={onStop} loading={isStopping}>
                            {stopLabel}
                        </Button>
                    )}
                </div>
            )}
        </div>
    );
};
