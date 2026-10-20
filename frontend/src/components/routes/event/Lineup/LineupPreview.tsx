import {t} from "@lingui/macro";
import {ActionIcon} from "@mantine/core";
import {IconDotsVertical, IconEye, IconEyeOff, IconPencil, IconPhotoOff, IconStar, IconTrash} from "@tabler/icons-react";
import {Event} from "../../../../types.ts";
import {EventLineup, LineupBillingTier, LineupEntry} from "../../../../api/lineup.client.ts";
import {ActionMenu, MenuItem} from "../../../common/ActionMenu";
import {useUpsertLineupEntry} from "../../../../mutations/useUpsertLineupEntry.ts";
import {useDeleteLineupEntry} from "../../../../mutations/useDeleteLineupEntry.ts";
import {BILLING_TIERS, billingTierLabel, initialsFor, profilePhoto} from "../../../../utilites/lineup.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {confirmationDialog} from "../../../../utilites/confirmationDialog.tsx";
import {useAccess} from "../../../../hooks/useAccess.ts";
import classes from "./Lineup.module.scss";

const tierHeading = (tier: LineupBillingTier) => {
    switch (tier) {
        case 'HEADLINER':
            return t`Headliners`;
        case 'FEATURED':
            return t`Featured`;
        default:
            return t`Everyone else`;
    }
};

interface LineupPreviewProps {
    event: Event;
    lineup: EventLineup;
    onOpenEntry: (entryId: number) => void;
}

export const LineupPreview = ({event, lineup, onOpenEntry}: LineupPreviewProps) => {
    const groups = BILLING_TIERS
        .map(tier => ({tier, entries: lineup.entries.filter(entry => entry.billing_tier === tier)}))
        .filter(group => group.entries.length > 0);

    return (
        <div className={classes.preview}>
            {groups.map(group => (
                <section key={group.tier} className={classes.previewGroup}>
                    <h3 className={classes.previewGroupTitle}>
                        {tierHeading(group.tier)}
                        <span>{group.entries.length}</span>
                    </h3>
                    <div className={classes.previewGrid} data-tier={group.tier.toLowerCase()}>
                        {group.entries.map(entry => (
                            <PreviewCard key={entry.id} event={event} entry={entry} onOpen={() => onOpenEntry(entry.id)}/>
                        ))}
                    </div>
                </section>
            ))}
        </div>
    );
};

const PreviewCard = ({event, entry, onOpen}: { event: Event, entry: LineupEntry, onOpen: () => void }) => {
    const {isReadOnly} = useAccess();
    const upsertMutation = useUpsertLineupEntry();
    const deleteMutation = useDeleteLineupEntry();
    const photo = profilePhoto(entry.profile);
    const name = entry.profile.name;

    const update = (changes: Partial<Pick<LineupEntry, 'billing_tier' | 'is_hidden'>>) => upsertMutation.mutate({
        eventId: event.id!,
        entryId: entry.id,
        entry: {
            billing_tier: changes.billing_tier ?? entry.billing_tier,
            role_label: entry.role_label,
            is_hidden: changes.is_hidden ?? !!entry.is_hidden,
        },
    }, {
        onError: () => showError(t`Could not update ${name}`),
    });

    const remove = () => confirmationDialog(
        t`Remove ${name} from this event's lineup? Their profile stays saved for other events.`,
        () => deleteMutation.mutate({eventId: event.id!, entryId: entry.id}, {
            onSuccess: () => showSuccess(t`Removed from the lineup`),
            onError: () => showError(t`Could not remove ${name}`),
        }),
        {confirm: t`Remove`},
    );

    const tierItems: MenuItem[] = BILLING_TIERS
        .filter(tier => tier !== entry.billing_tier)
        .map(tier => {
            const tierName = billingTierLabel(tier);
            return {
                label: t`Make ${tierName}`,
                icon: <IconStar size={14}/>,
                onClick: () => update({billing_tier: tier}),
                requiresEdit: true,
            };
        });

    return (
        <div className={classes.previewCard} data-hidden={entry.is_hidden || undefined} data-testid={`lineup-card-${entry.id}`}>
            <button type="button" className={classes.previewMedia} onClick={onOpen} aria-label={isReadOnly ? t`View ${name}` : t`Edit ${name}`}>
                {photo
                    ? <img src={photo.url} alt="" loading="lazy" style={{backgroundColor: photo.avg_colour ?? undefined}}/>
                    : (
                        <span className={classes.previewMonogram}>
                            {initialsFor(name)}
                            <span className={classes.previewNoPhoto}><IconPhotoOff size={12}/>{t`No photo`}</span>
                        </span>
                    )}
                {entry.is_hidden && <span className={classes.previewHiddenBadge}><IconEyeOff size={12}/>{t`Hidden`}</span>}
            </button>
            <div className={classes.previewBody}>
                <button type="button" className={classes.previewText} onClick={onOpen}>
                    <span className={classes.previewName}>{name}</span>
                    {(entry.role_label || entry.profile.tagline) && (
                        <span className={classes.previewSubtitle}>{entry.role_label || entry.profile.tagline}</span>
                    )}
                </button>
                <ActionMenu
                    itemsGroups={[
                        {
                            label: t`Lineup`,
                            items: [
                                {label: isReadOnly ? t`View details` : t`Edit details`, icon: <IconPencil size={14}/>, onClick: onOpen},
                                ...tierItems,
                                {
                                    label: entry.is_hidden ? t`Show on event page` : t`Hide from event page`,
                                    icon: entry.is_hidden ? <IconEye size={14}/> : <IconEyeOff size={14}/>,
                                    onClick: () => update({is_hidden: !entry.is_hidden}),
                                    requiresEdit: true,
                                },
                            ],
                        },
                        {
                            label: t`Danger zone`,
                            showDividerAbove: true,
                            items: [{
                                label: t`Remove from lineup`,
                                icon: <IconTrash size={14}/>,
                                color: 'red',
                                onClick: remove,
                                requiresEdit: true,
                            }],
                        },
                    ]}
                    target={(
                        <ActionIcon variant="subtle" color="gray" size="sm" aria-label={t`Actions for ${name}`}>
                            <IconDotsVertical size={16}/>
                        </ActionIcon>
                    )}
                />
            </div>
        </div>
    );
};
