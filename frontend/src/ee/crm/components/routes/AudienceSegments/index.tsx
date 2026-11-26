import {useState} from "react";
import {Link, useParams} from "react-router";
import {plural, t} from "@lingui/macro";
import {ActionIcon, Button, Menu, Skeleton} from "@mantine/core";
import {IconDotsVertical, IconFilter, IconPencil, IconPlus, IconTrash} from "@tabler/icons-react";
import {CreateButton} from "../../../../../components/common/CreateButton";
import {getClientLocale, toIntlLocale} from "../../../../../locales.ts";
import {IdParam} from "../../../../../types.ts";
import {confirmationDialog} from "../../../../../utilites/confirmationDialog.tsx";
import {firstApiError} from "../../../../../utilites/apiErrors.ts";
import {showError, showSuccess} from "../../../../../utilites/notifications.tsx";
import {useCrmAccess} from "../../../hooks/useCrmAccess.ts";
import {useCrmReady} from "../../../hooks/useCrmReady.ts";
import {useTextMessagesVisible} from "../../../hooks/useTextMessagesVisible.ts";
import {useDeleteCrmSegment} from "../../../mutations/useDeleteCrmSegment.ts";
import {useGetCrmSegmentCount} from "../../../queries/useGetCrmSegmentCount.ts";
import {useGetCrmSegments} from "../../../queries/useGetCrmSegments.ts";
import {CrmSegment, CrmSegmentPresetKey, CrmSegmentUsage} from "../../../types.ts";
import {formatCount} from "../../../utilites/format.ts";
import {automationTitle, isEveryoneOnOneChannel, presetDescription, presetLabel} from "../../../utilites/labels.ts";
import {AudienceShell} from "../../AudienceShell";
import {LoadError} from "../../LoadError";
import {SegmentModal} from "../../SegmentModal";
import shared from "../../Audience.module.scss";
import classes from "./Segments.module.scss";

const usageLabel = (usage: CrmSegmentUsage): string => {
    if (usage.type === 'AUTOMATION' && usage.trigger) {
        const title = usage.name ?? automationTitle(usage.trigger);
        return usage.status === 'ENABLED' ? t`${title} (automation, on)` : t`${title} (automation, off)`;
    }
    const name = usage.name ?? '';
    if (usage.status === 'SCHEDULED') {
        return t`${name} (scheduled campaign)`;
    }
    return usage.status === 'REJECTED' ? t`${name} (campaign not approved)` : t`${name} (draft campaign)`;
};

const usageList = (usages: CrmSegmentUsage[]): string =>
    new Intl.ListFormat(toIntlLocale(getClientLocale()), {style: 'long', type: 'conjunction'}).format(usages.map(usageLabel));

const UNCOUNTED = '–';

const SegmentFigures = ({organizerId, segment}: { organizerId: IdParam, segment: CrmSegment }) => {
    const countQuery = useGetCrmSegmentCount(organizerId, {segmentId: segment.id}, !segment.has_restricted_rules);
    const count = countQuery.data;

    if (segment.has_restricted_rules) {
        return <span>{t`Filters by spend, which your role can't see`}</span>;
    }

    if (countQuery.isLoading) {
        return (
            <>
                <Skeleton component="span" className={classes.countSkeleton} height={10} width={64}/>
                <Skeleton component="span" className={classes.countSkeleton} height={10} width={84}/>
            </>
        );
    }

    if (!count) {
        return <span title={t`We couldn't count this segment`}>{UNCOUNTED}</span>;
    }

    return (
        <>
            <span>{plural(count.contacts_count, {one: '# person', other: '# people'})}</span>
            <span>{plural(count.mailable_count, {one: '# subscribed', other: '# subscribed'})}</span>
        </>
    );
};

const PresetFigures = ({organizerId, preset}: { organizerId: IdParam, preset: CrmSegmentPresetKey }) => {
    const countQuery = useGetCrmSegmentCount(organizerId, {preset});
    const count = countQuery.data;
    const showsSubscribed = !isEveryoneOnOneChannel(preset);

    return (
        <>
            <span className={classes.cardValue}>
                {countQuery.isLoading ? <Skeleton component="span" className={classes.countSkeleton} height={18} width={56}/> : count ? formatCount(count.contacts_count) : UNCOUNTED}
            </span>
            <span className={classes.cardHint}>{presetDescription(preset)}</span>
            {showsSubscribed && (
                <span className={classes.cardHint}>
                    {countQuery.isLoading
                        ? <Skeleton component="span" className={classes.countSkeleton} height={10} width={84}/>
                        : count ? plural(count.mailable_count, {one: '# subscribed', other: '# subscribed'}) : UNCOUNTED}
                </span>
            )}
        </>
    );
};

const AudienceSegments = () => {
    const {organizerId} = useParams();
    const isReady = useCrmReady(organizerId);
    const segmentsQuery = useGetCrmSegments(organizerId, isReady);
    const deleteSegment = useDeleteCrmSegment();
    const {canChange, canRemove} = useCrmAccess();
    const showTextMessages = useTextMessagesVisible();
    const [modal, setModal] = useState<{ segment?: CrmSegment } | null>(null);
    const basePath = `/manage/organizer/${organizerId}/audience`;
    const segments = segmentsQuery.data;
    const presets = (segments?.presets ?? []).filter(preset => showTextMessages || preset.key !== 'sms_subscribers');

    const handleDelete = (segment: CrmSegment) => {
        const blocking = segment.usages.filter(usage => usage.blocks_deletion);

        if (blocking.length > 0) {
            const usages = usageList(blocking);
            showError(t`This segment can't be deleted while it's still in use: ${usages}. Cancel the campaign or change who the automation sends to first.`);
            return;
        }

        const usages = usageList(segment.usages);
        const name = segment.name;

        confirmationDialog(
            t`Delete the segment "${name}"?`,
            () => deleteSegment.mutate({organizerId, segmentId: segment.id}, {
                onSuccess: () => showSuccess(t`Segment deleted`),
                onError: error => showError(firstApiError(error, t`We couldn't delete this segment. Please try again.`)),
            }),
            {
                confirm: t`Delete segment`,
                destructive: true,
                description: segment.usages.length > 0
                    ? t`The people in it aren't affected. It's still in use: ${usages}. Choose who they send to before they can go out again.`
                    : t`The people in it aren't affected.`,
            },
        );
    };

    return (
        <AudienceShell
            actions={canChange && (
                <CreateButton color="green" rightSection={<IconPlus/>} onClick={() => setModal({})} data-testid="segment-create-button">
                    {t`New segment`}
                </CreateButton>
            )}
        >
            <div className={classes.sectionHeading}>
                <h2>{t`Your segments`}</h2>
            </div>

            <div className={classes.table}>
                {segmentsQuery.isLoading && <div className={classes.listRow}><Skeleton height={36} radius="md"/></div>}

                {segmentsQuery.isError && (
                    <LoadError message={t`We couldn't load your segments.`} onRetry={() => segmentsQuery.refetch()}/>
                )}

                {segments && segments.data.length === 0 && (
                    <div className={shared.empty}>
                        <IconFilter size={22} stroke={1.5}/>
                        <p>{t`No segments of your own yet. Combine rules like "booked 3+ events" and "not seen in 6 months" to build one.`}</p>
                        {canChange && (
                            <Button variant="default" size="xs" onClick={() => setModal({})}>{t`Create a segment`}</Button>
                        )}
                    </div>
                )}

                {segments?.data.map(segment => (
                    <div key={segment.id} className={classes.listRow} data-testid="segment-row">
                        <div className={classes.listTitle}>
                            <Link to={`${basePath}/contacts?segment=saved:${segment.id}`}>{segment.name}</Link>
                            <p>
                                {segment.description || plural(segment.definition.rules.length, {one: '# rule', other: '# rules'})}
                            </p>
                        </div>
                        <div className={classes.listFigures}>
                            <SegmentFigures organizerId={organizerId} segment={segment}/>
                        </div>
                        {canRemove && (
                            <Menu position="bottom-end" withinPortal>
                                <Menu.Target>
                                    <ActionIcon className={classes.rowActions} variant="subtle" color="gray" aria-label={t`Segment actions`}>
                                        <IconDotsVertical size={16}/>
                                    </ActionIcon>
                                </Menu.Target>
                                <Menu.Dropdown>
                                    {canChange && (
                                        <Menu.Item leftSection={<IconPencil size={14}/>} onClick={() => setModal({segment})}>
                                            {t`Edit`}
                                        </Menu.Item>
                                    )}
                                    <Menu.Item color="red" leftSection={<IconTrash size={14}/>} onClick={() => handleDelete(segment)}>
                                        {t`Delete`}
                                    </Menu.Item>
                                </Menu.Dropdown>
                            </Menu>
                        )}
                    </div>
                ))}
            </div>

            {!segmentsQuery.isError && (
                <div className={classes.sectionHeading}>
                    <h2>{t`Ready-made`}</h2>
                </div>
            )}

            <div className={classes.cards}>
                {segmentsQuery.isLoading && Array.from({length: 4}).map((_, index) => <Skeleton key={index} height={112} radius="lg"/>)}
                {presets.map(preset => (
                    <Link
                        key={preset.key}
                        to={`${basePath}/contacts?segment=preset:${preset.key}`}
                        className={classes.card}
                    >
                        <span className={classes.cardTitle}>{presetLabel(preset.key)}</span>
                        <PresetFigures organizerId={organizerId} preset={preset.key}/>
                    </Link>
                ))}
            </div>

            {modal && <SegmentModal organizerId={organizerId} segment={modal.segment} onClose={() => setModal(null)}/>}
        </AudienceShell>
    );
};

export default AudienceSegments;
