import React from "react";
import { t } from "@lingui/macro";
import { useDisclosure } from "@mantine/hooks";
import { Button } from '@mantine/core';
import {
    IconChevronRight,
    IconExternalLink,
    IconEye,
    IconEyeOff,
    IconShare
} from "@tabler/icons-react";
import { NavLink } from "react-router";
import classes from '../Topbar/Topbar.module.scss';
import { ShareModal } from "../../../modals/ShareModal";
import {StatusToggleConfig} from "../../AppLayout/types.ts";

interface EventTopBarContentProps {
    entityType: 'event' | 'organizer';
    entityId: string;
    entityData: any;
    statusToggleConfig: StatusToggleConfig;
}

export const EventTopBarContent: React.FC<EventTopBarContentProps> = ({
                                                                          entityType,
                                                                          entityId,
                                                                          entityData,
                                                                          statusToggleConfig
                                                                      }) => {
    const [shareModalOpened, { open: openShareModal, close: closeShareModal }] = useDisclosure(false);

    return (
        <>
            {/* Status toggle */}
            <div className={classes.statusToggleContainer}>
                <Button
                    onClick={statusToggleConfig.onToggle}
                    size="sm"
                    className={`${classes.statusToggleButton} ${
                        statusToggleConfig.status === 'DRAFT' ? classes.draftButton : classes.liveButton
                    }`}
                    leftSection={
                        statusToggleConfig.status === 'DRAFT'
                            ? <IconEyeOff size={16}/>
                            : <IconEye size={16}/>
                    }
                    rightSection={<IconChevronRight size={14}/>}
                >
                    {statusToggleConfig.status === 'DRAFT'
                        ? <span>{t`Draft`} <span className={classes.statusAction}>
                        {statusToggleConfig.statusMessages?.draft || t`- Click to Publish`}
                        </span></span>
                        : <span>{t`Live`} <span className={classes.statusAction}>
                        {statusToggleConfig.statusMessages?.live || t`- Click to Unpublish`}
                        </span></span>
                    }
                </Button>
            </div>

            {/* Preview button */}
            {entityType === 'event' && entityData && (
                <Button
                    component={NavLink}
                    to={`/${entityType}/${entityId}/${entityData?.slug}`}
                    target={'_blank'}
                    variant={'transparent'}
                    leftSection={<IconExternalLink size={17}/>}
                    className={classes.entityPageButton}
                    title={t`Preview ${entityType} page`}
                >
                    <div className={classes.entityPageButtonText}>
                        <span className={classes.desktop}>
                            {t`Preview ${entityType} page`}
                        </span>
                        <span className={classes.mobile}>
                            {t`${entityType.charAt(0).toUpperCase() + entityType.slice(1)} Page`}
                        </span>
                    </div>
                </Button>
            )}

            {/* Share button in breadcrumbs row - added via portal or parent component */}
            <div className={classes.shareButton} style={{ position: 'absolute', right: '16px', top: '68px' }}>
                <Button
                    onClick={openShareModal}
                    variant="transparent"
                    leftSection={<IconShare size={16}/>}
                >
                    {t`Share ${entityType.charAt(0).toUpperCase() + entityType.slice(1)}`}
                </Button>

                {entityData && (
                    <ShareModal
                        event={entityData}
                        opened={shareModalOpened}
                        onClose={closeShareModal}
                    />
                )}
            </div>
        </>
    );
};
