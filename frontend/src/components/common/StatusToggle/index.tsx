import React, {useEffect, useState} from 'react';
import {Button} from '@mantine/core';
import {IconEye} from '@tabler/icons-react';
import {t} from '@lingui/macro';
import {showError, showSuccess} from '../../../utilites/notifications';
import {confirmationDialog} from '../../../utilites/confirmationDialog';
import {createLazyModule} from '../../../utilites/lazyModule';
import {useUpdateOrganizerStatus} from '../../../mutations/useUpdateOrganizerStatus';
import {useAccess} from '../../../hooks/useAccess';
import {Event, IdParam} from '../../../types';
import type {EventPublishFlowProps} from './EventPublishFlow';
import classes from './StatusToggle.module.scss';

type StatusToggleProps =
    | {
    entityType: 'event';
    event: Event;
}
    | {
    entityType: 'organizer';
    entityId: IdParam;
    currentStatus: 'DRAFT' | 'LIVE';
    onSuccess?: () => void;
};

interface StatusBannerProps {
    message: string;
    onPublish?: () => void;
    loading?: boolean;
}

const StatusBanner = ({message, onPublish, loading}: StatusBannerProps) => (
    <div className={classes.banner} data-status-banner="">
        <div className={classes.content}>
            <span className={classes.message}>{message}</span>
            {onPublish && (
                <Button
                    onClick={onPublish}
                    loading={loading}
                    size="xs"
                    variant="white"
                    leftSection={<IconEye size={14}/>}
                    className={classes.publishButton}
                >
                    {t`Publish`}
                </Button>
            )}
        </div>
    </div>
);

const eventPublishFlowModule = createLazyModule(() => import('./EventPublishFlow'));

const LazyEventPublishFlow = (props: EventPublishFlowProps) => {
    const module = eventPublishFlowModule.useModule();

    return module ? <module.EventPublishFlow {...props}/> : null;
};

const EventDraftBanner = ({onPublish}: { onPublish: () => void }) => {
    const {isLoaded, isReadOnly} = useAccess();
    const canPublish = isLoaded && !isReadOnly;
    const [isLoadingFlow, setIsLoadingFlow] = useState(false);

    useEffect(() => {
        if (canPublish) {
            eventPublishFlowModule.load().catch(() => undefined);
        }
    }, [canPublish]);

    const handlePublish = () => {
        setIsLoadingFlow(true);
        eventPublishFlowModule.load().then(
            onPublish,
            () => showError(t`Something went wrong. Please try again.`),
        ).finally(() => setIsLoadingFlow(false));
    };

    return (
        <StatusBanner
            message={t`This event is not published yet`}
            onPublish={canPublish ? handlePublish : undefined}
            loading={isLoadingFlow}
        />
    );
};

const EventStatusToggle = ({event}: { event: Event }) => {
    const [isFlowOpen, setIsFlowOpen] = useState(false);
    const [isPublished, setIsPublished] = useState(false);

    return (
        <>
            {event.status === 'PENDING_MANUAL_REVIEW' && (
                <StatusBanner message={t`This event is pending manual review and is not visible to the public`}/>
            )}

            {event.status === 'DRAFT' && !isPublished && (
                <EventDraftBanner onPublish={() => setIsFlowOpen(true)}/>
            )}

            {isFlowOpen && (
                <LazyEventPublishFlow
                    event={event}
                    onPublished={() => setIsPublished(true)}
                    onClose={() => setIsFlowOpen(false)}
                />
            )}
        </>
    );
};

const OrganizerStatusToggle = ({entityId, currentStatus, onSuccess}: {
    entityId: IdParam;
    currentStatus: 'DRAFT' | 'LIVE';
    onSuccess?: () => void;
}) => {
    const mutation = useUpdateOrganizerStatus();
    const {isLoaded, isReadOnly, canManageOrganizer} = useAccess();
    const canPublish = isLoaded && !isReadOnly && canManageOrganizer(entityId);

    const handlePublish = () => {
        confirmationDialog(
            t`Are you sure you want to publish this organizer profile? Once published, it will be visible to the public.`,
            () => {
                mutation.mutate({organizerId: entityId, status: 'LIVE'}, {
                    onSuccess: () => {
                        showSuccess(t`Organizer status updated`);
                        onSuccess?.();
                    },
                    onError: (error: any) => {
                        showError(error?.response?.data?.message || t`Organizer status update failed. Please try again later`);
                    }
                });
            }
        );
    };

    if (currentStatus === 'LIVE') {
        return null;
    }

    return (
        <StatusBanner
            message={t`This organizer profile is not published yet`}
            onPublish={canPublish ? handlePublish : undefined}
            loading={mutation.isPending}
        />
    );
};

export const StatusToggle: React.FC<StatusToggleProps> = (props) => {
    if (props.entityType === 'event') {
        return <EventStatusToggle event={props.event}/>;
    }

    return (
        <OrganizerStatusToggle
            entityId={props.entityId}
            currentStatus={props.currentStatus}
            onSuccess={props.onSuccess}
        />
    );
};
