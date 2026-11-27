import {ReactNode, useEffect, useRef} from "react";
import {useLocation, useParams} from "react-router";
import {plural, t} from "@lingui/macro";
import {Button, Progress, Skeleton, VisuallyHidden} from "@mantine/core";
import {IconChartDots, IconLock, IconMailHeart, IconUsersGroup} from "@tabler/icons-react";
import {useQueryClient} from "@tanstack/react-query";
import {PageBody} from "../../../../components/common/PageBody";
import {FeatureFlag} from "../../../../constants/featureFlags.ts";
import {useGetMe} from "../../../../queries/useGetMe.ts";
import {firstApiError} from "../../../../utilites/apiErrors.ts";
import {showError} from "../../../../utilites/notifications.tsx";
import {useLicenceLockedCopy} from "../../../licensing/components/LicenceLockedCallout";
import {useLicensedFeature} from "../../../licensing/hooks/useLicensedFeature.ts";
import {useCrmAccess} from "../../hooks/useCrmAccess.ts";
import {isAudienceBuilt} from "../../hooks/useCrmReady.ts";
import {useTextMessagesVisible} from "../../hooks/useTextMessagesVisible.ts";
import {useStartCrmSync} from "../../mutations/useStartCrmSync.ts";
import {audienceMembershipKeys, crmKeys, invalidateCrm} from "../../queries/crmQueryKeys.ts";
import {useGetCrmSyncStatus} from "../../queries/useGetCrmSyncStatus.ts";
import {LoadError} from "../LoadError";
import sharedClasses from "../Audience.module.scss";
import classes from "./AudienceShell.module.scss";
import {Notice} from "../Notice";

interface AudienceShellProps {
    actions?: ReactNode;
    isTextCampaign?: boolean;
    children: ReactNode;
}

const sections = (hasTextMessages: boolean): Record<string, {title: string, subheading: string}> => ({
    '': {
        title: t`Audience`,
        subheading: t`Everyone who has booked, attended or joined a waitlist across your events, in one place.`,
    },
    contacts: {
        title: t`Contacts`,
        subheading: t`One profile per person, built from every order, ticket, check-in and signup.`,
    },
    segments: {
        title: t`Segments`,
        subheading: t`Saved groups of people that stay up to date as your audience changes.`,
    },
    forms: {
        title: t`Signup forms`,
        subheading: t`Grow your list beyond ticket buyers. Everyone confirms by email before they're added.`,
    },
    campaigns: {
        title: t`Campaigns`,
        subheading: hasTextMessages
            ? t`Email or text the people who opted in, and see how each campaign did.`
            : t`Email the people who opted in, and see who opened, clicked and bought.`,
    },
    templates: {
        title: t`Templates`,
        subheading: t`Reusable subject lines and messages, shared with your team.`,
    },
    automations: {
        title: t`Automations`,
        subheading: t`Emails that send themselves when something happens.`,
    },
});

const NotAvailable = () => {
    const {isUnlicensed} = useLicensedFeature(FeatureFlag.CRM);

    return (
        <div className={sharedClasses.panel}>
            <div className={sharedClasses.empty}>
                <IconLock size={22} stroke={1.5}/>
                <p><strong>{t`Audience isn't available on this account`}</strong></p>
                <p>
                    {isUnlicensed
                        ? t`Audience isn't included in this installation's licence. Once it's added, your contacts, segments and campaigns appear here.`
                        : t`Audience hasn't been turned on for this account yet.`}
                </p>
            </div>
        </div>
    );
};

export const AudienceShell = ({actions, isTextCampaign = false, children}: AudienceShellProps) => {
    const {organizerId} = useParams();
    const location = useLocation();
    const queryClient = useQueryClient();
    const meQuery = useGetMe();
    const {isVisible, isLocked, canChange} = useCrmAccess();
    const lockedCopy = useLicenceLockedCopy();
    const hasTextMessages = useTextMessagesVisible();
    const statusQuery = useGetCrmSyncStatus(organizerId, isVisible);
    const status = statusQuery.data;
    const startSync = useStartCrmSync();
    const wasSyncing = useRef(false);
    const lastSyncedAt = useRef<string | null | undefined>(undefined);

    useEffect(() => {
        if (!status) {
            return;
        }
        const finishedSyncing = status.status !== 'SYNCING' && wasSyncing.current;
        const syncedAtChanged = lastSyncedAt.current !== undefined && lastSyncedAt.current !== status.synced_at;
        wasSyncing.current = status.status === 'SYNCING';
        lastSyncedAt.current = status.synced_at;
        if (finishedSyncing || syncedAtChanged) {
            void invalidateCrm(queryClient, [...audienceMembershipKeys(organizerId), crmKeys.contacts(organizerId), crmKeys.tags(organizerId)]);
        }
    }, [status?.status, status?.synced_at, organizerId, queryClient]);

    const isBuilt = isAudienceBuilt(status);
    const isRefreshing = isBuilt && status?.status === 'SYNCING';
    const hasRefreshFailed = isBuilt && status?.status === 'FAILED';
    const refreshPercent = Math.round(status?.progress_percent ?? 0);
    const [, sectionKey = '', detailKey = ''] = location.pathname.split('/audience')[1]?.split('/') ?? [];
    const isDetailPage = detailKey !== '';

    const handleStart = () => startSync.mutate({organizerId}, {
        onError: error => showError(firstApiError(error, isBuilt
            ? t`We couldn't refresh your audience. Please try again later.`
            : t`We couldn't start building your audience. Please try again.`)),
    });
    const allSections = sections(hasTextMessages || isTextCampaign);
    const section = allSections[sectionKey] ?? allSections[''];
    const isLoading = meQuery.isLoading || (isVisible && statusQuery.isLoading);

    return (
        <PageBody>
            <div className={sharedClasses.page}>
                {isDetailPage ? (
                    <VisuallyHidden component="h1">{section.title}</VisuallyHidden>
                ) : (
                    <header className={classes.pageHeader}>
                        <h1 className={classes.title}>{section.title}</h1>
                        <p className={classes.subheading}>{section.subheading}</p>
                        {isBuilt && (
                            <div className={classes.pageActions}>
                                {isRefreshing && (
                                    <span className={classes.syncStatus} role="status">
                                        {t`Refreshing… ${refreshPercent}%`}
                                    </span>
                                )}
                                {actions}
                            </div>
                        )}
                    </header>
                )}

                {isLocked && isVisible && <Notice title={lockedCopy.title} data-testid="licence-locked-callout">{lockedCopy.body}</Notice>}

                {isLoading && <Skeleton height={220} radius="lg"/>}

                {!isLoading && !isVisible && <NotAvailable/>}

                {statusQuery.isError && (
                    <div className={sharedClasses.panel}>
                        <LoadError message={t`We couldn't load your audience.`} onRetry={() => statusQuery.refetch()}/>
                    </div>
                )}

                {status && !isBuilt && (
                    <div className={classes.setup}>
                        <div className={classes.setupMain}>
                            {status.status === 'SYNCING' ? (
                                <>
                                    <h2>{t`Building your audience…`}</h2>
                                    <p>{t`We're reading your past orders, tickets and check-ins. You can leave this page; it carries on in the background.`}</p>
                                    <div className={classes.progress}>
                                        <Progress value={Math.max(status.progress_percent, 4)} animated aria-label={t`Progress`}/>
                                        <div className={classes.progressLabel}>
                                            {plural(status.contacts_count, {one: '# contact found so far', other: '# contacts found so far'})}
                                        </div>
                                    </div>
                                </>
                            ) : (
                                <>
                                    <h2>{t`See who your audience really is`}</h2>
                                    <p>
                                        {status.status === 'FAILED'
                                            ? t`Something went wrong while building your audience. Nothing was lost.`
                                            : t`We'll build a profile for every person from the orders and check-ins you already have. Nothing changes for your attendees.`}
                                    </p>
                                    {canChange && (
                                        <Button
                                            size="md"
                                            onClick={handleStart}
                                            loading={startSync.isPending}
                                            data-testid="audience-start-button"
                                        >
                                            {status.status === 'FAILED' ? t`Try again` : t`Build my audience`}
                                        </Button>
                                    )}
                                    {!canChange && !isLocked && (
                                        <p>
                                            {status.status === 'FAILED'
                                                ? t`Someone with edit access needs to try again.`
                                                : t`Someone with edit access needs to turn this on.`}
                                        </p>
                                    )}
                                </>
                            )}
                        </div>
                        <div className={classes.setupAside}>
                            <div className={classes.setupPoint}>
                                <IconUsersGroup size={20} stroke={1.6}/>
                                <div>
                                    <strong>{t`One profile per person`}</strong>
                                    <span>{t`Every order, ticket, check-in and waitlist join, across all your events.`}</span>
                                </div>
                            </div>
                            <div className={classes.setupPoint}>
                                <IconChartDots size={20} stroke={1.6}/>
                                <div>
                                    <strong>{t`Know who comes back`}</strong>
                                    <span>{t`Repeat rate, regulars who have drifted away, and who never turns up.`}</span>
                                </div>
                            </div>
                            <div className={classes.setupPoint}>
                                <IconMailHeart size={20} stroke={1.6}/>
                                <div>
                                    <strong>{t`Reach the right people`}</strong>
                                    <span>{t`Group people by what they did, then email the ones who opted in.`}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {hasRefreshFailed && (
                    <Notice title={t`The last audience refresh didn't finish`}>
                        {t`Your audience is still here and new orders keep being added. Some older details may be out of date until a refresh finishes.`}
                        {canChange && (
                            <div className={classes.noticeAction}>
                                <Button
                                    variant="default"
                                    size="xs"
                                    onClick={handleStart}
                                    loading={startSync.isPending}
                                    data-testid="audience-refresh-retry-button"
                                >
                                    {t`Try again`}
                                </Button>
                            </div>
                        )}
                    </Notice>
                )}

                {isBuilt && children}
            </div>
        </PageBody>
    );
};
