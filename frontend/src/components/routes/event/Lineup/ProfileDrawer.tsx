import {t} from "@lingui/macro";
import {useForm} from "@mantine/form";
import {ActionIcon, Alert, Avatar, Badge, Button, SegmentedControl, Switch, TextInput} from "@mantine/core";
import {NavLink} from "react-router";
import {IconDotsVertical, IconInfoCircle, IconTrash, IconUserMinus} from "@tabler/icons-react";
import {SideDrawer, SideDrawerHeading, SideDrawerSection, SideDrawerStats} from "../../../common/SideDrawer";
import {Editor} from "../../../common/Editor";
import {ActionMenu} from "../../../common/ActionMenu";
import {UserGeneratedContent} from "../../../common/UserGeneratedContent";
import {IdParam} from "../../../../types.ts";
import {LineupBillingTier, LineupEntry, Profile} from "../../../../api/lineup.client.ts";
import {useUpsertProfile} from "../../../../mutations/useUpsertProfile.ts";
import {useUpsertLineupEntry} from "../../../../mutations/useUpsertLineupEntry.ts";
import {useDeleteLineupEntry} from "../../../../mutations/useDeleteLineupEntry.ts";
import {useDeleteProfile} from "../../../../mutations/useDeleteProfile.ts";
import {useAccess} from "../../../../hooks/useAccess.ts";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.tsx";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {confirmationDialog} from "../../../../utilites/confirmationDialog.tsx";
import {BILLING_TIERS, billingTierLabel, initialsFor, profilePhoto} from "../../../../utilites/lineup.ts";
import {ProfileLinksField} from "./ProfileLinksField.tsx";
import {EventStatusBadge} from "./EventStatusBadge.tsx";
import {ProfileImagesField} from "./ProfileImagesField.tsx";
import classes from "./Lineup.module.scss";

interface ProfileDrawerProps {
    organizerId: IdParam;
    eventId?: IdParam;
    entry?: LineupEntry;
    profile?: Profile;
    slotCount?: number;
    onCreated?: (profile: Profile) => void;
    onClose: () => void;
}

interface ProfileFormValues {
    name: string;
    tagline: string;
    bio: string;
    links: string[];
    billing_tier: LineupBillingTier;
    role_label: string;
    is_hidden: boolean;
}

const PROFILE_FIELDS: (keyof ProfileFormValues)[] = ['name', 'tagline', 'bio', 'links'];
const ENTRY_FIELDS: (keyof ProfileFormValues)[] = ['billing_tier', 'role_label', 'is_hidden'];

export const ProfileDrawer = (props: ProfileDrawerProps) => {
    const profile = props.entry?.profile ?? props.profile;

    return <ProfileDrawerForm key={`${props.entry?.id ?? 'profile'}-${profile?.id ?? 'new'}`} {...props}/>;
};

const ProfileDrawerForm = ({organizerId, eventId, entry, profile: profileProp, slotCount = 0, onCreated, onClose}: ProfileDrawerProps) => {
    const {isReadOnly, canManageOrganizer} = useAccess();
    const canEditProfile = canManageOrganizer(organizerId) && !isReadOnly;
    const canEditEntry = !!entry && !isReadOnly;
    const profileMutation = useUpsertProfile();
    const entryMutation = useUpsertLineupEntry();
    const deleteEntryMutation = useDeleteLineupEntry();
    const deleteProfileMutation = useDeleteProfile();
    const handleFormError = useFormErrorResponseHandler();
    const profile = entry?.profile ?? profileProp;

    const form = useForm<ProfileFormValues>({
        initialValues: {
            name: profile?.name ?? '',
            tagline: profile?.tagline ?? '',
            bio: profile?.bio ?? '',
            links: profile?.links.length ? profile.links.map(link => link.url) : [''],
            billing_tier: entry?.billing_tier ?? 'STANDARD',
            role_label: entry?.role_label ?? '',
            is_hidden: !!entry?.is_hidden,
        },
        validate: {
            name: value => value.trim() ? null : t`Name is required`,
        },
    });

    const isSaving = profileMutation.isPending || entryMutation.isPending;
    const profileName = profile?.name ?? '';

    const handleSubmit = async (values: ProfileFormValues) => {
        const linkRows = values.links
            .map((link, row) => ({url: link.trim(), row}))
            .filter(link => link.url);
        const profileChanged = canEditProfile && (!profile || PROFILE_FIELDS.some(field => form.isDirty(field)));
        const entryChanged = canEditEntry && ENTRY_FIELDS.some(field => form.isDirty(field));

        try {
            let savedProfile = profile;

            if (profileChanged) {
                const {data} = await profileMutation.mutateAsync({
                    organizerId,
                    profileId: profile?.id,
                    profile: {
                        name: values.name.trim(),
                        tagline: values.tagline.trim() || null,
                        bio: values.bio || null,
                        links: linkRows.map(link => link.url),
                    },
                });
                savedProfile = data;
            }

            if (entryChanged && entry && eventId) {
                await entryMutation.mutateAsync({
                    eventId,
                    entryId: entry.id,
                    entry: {
                        billing_tier: values.billing_tier,
                        role_label: values.role_label.trim() || null,
                        is_hidden: values.is_hidden,
                    },
                });
            }

            if (!profile && savedProfile) {
                showSuccess(t`Profile created. You can add photos now.`);
                onCreated?.(savedProfile);
                return;
            }

            if (profileChanged || entryChanged) {
                showSuccess(t`Saved`);
            }
            onClose();
        } catch (error) {
            handleFormError(form, error);
            form.setErrors(errors => Object.fromEntries(Object.entries(errors).map(([field, message]) => {
                const linkIndex = /^links\.(\d+)$/.exec(field)?.[1];
                return [linkIndex === undefined ? field : `links.${linkRows[Number(linkIndex)]?.row ?? linkIndex}`, message];
            })));
        }
    };

    const removeFromLineup = () => entry && eventId && confirmationDialog(
        t`Remove ${profileName} from this event's lineup? Their profile stays saved for other events.`,
        () => deleteEntryMutation.mutate({eventId, entryId: entry.id}, {
            onSuccess: () => {
                showSuccess(t`Removed from the lineup`);
                onClose();
            },
            onError: () => showError(t`Could not remove ${profileName}`),
        }),
        {confirm: t`Remove`},
    );

    const deleteProfile = () => profile && confirmationDialog(
        t`Delete ${profileName} everywhere? They will be removed from every event lineup and schedule for this organizer. This can't be undone.`,
        () => deleteProfileMutation.mutate({organizerId, profileId: profile.id}, {
            onSuccess: () => {
                showSuccess(t`Profile deleted`);
                onClose();
            },
            onError: () => showError(t`Could not delete ${profileName}`),
        }),
        {confirm: t`Delete profile`},
    );

    const photo = profilePhoto(profile);
    const fieldsDisabled = !canEditProfile;
    const subtitle = entry
        ? [billingTierLabel(form.values.billing_tier), form.values.role_label.trim()].filter(Boolean).join(' · ')
        : profile?.tagline || t`Saved to this organizer so you can reuse it at any event`;

    return (
        <SideDrawer
            opened
            onClose={onClose}
            header={(
                <SideDrawerHeading
                    media={(
                        <Avatar src={photo?.url} alt="" radius="md" size={48} color="primary">
                            {initialsFor(form.values.name || '?')}
                        </Avatar>
                    )}
                    title={form.values.name.trim() || profile?.name || t`New profile`}
                    subtitle={subtitle}
                />
            )}
            actions={profile && (canEditEntry || canEditProfile) && (
                <ActionMenu
                    itemsGroups={[{
                        label: t`Actions`,
                        items: [
                            {
                                label: t`Remove from this lineup`,
                                icon: <IconUserMinus size={14}/>,
                                onClick: removeFromLineup,
                                visible: !!entry,
                                requiresEdit: true,
                            },
                            {
                                label: t`Delete profile everywhere`,
                                icon: <IconTrash size={14}/>,
                                color: 'red',
                                onClick: deleteProfile,
                                visible: canEditProfile,
                                requiresEdit: true,
                            },
                        ],
                    }]}
                    target={(
                        <ActionIcon variant="subtle" color="gray" size="lg" radius="xl" aria-label={t`More actions`}>
                            <IconDotsVertical size={18}/>
                        </ActionIcon>
                    )}
                />
            )}
            footer={(canEditEntry || canEditProfile) && (
                <>
                    <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                    <Button
                        type="submit"
                        form="lineup-profile-form"
                        loading={isSaving}
                        data-testid="lineup-profile-save-button"
                    >
                        {profile ? t`Save changes` : t`Create profile`}
                    </Button>
                </>
            )}
        >
            {profile && (
                <SideDrawerStats stats={[
                    {label: t`Photos`, value: profile.images.length},
                    {label: t`Links`, value: profile.links.length},
                    ...(entry
                        ? [
                            {label: t`Time slots`, value: slotCount},
                            {label: t`Bio`, value: profile.bio ? t`Added` : t`Missing`},
                        ]
                        : [{label: t`Events`, value: profile.appearances?.length ?? 0}]),
                ]}/>
            )}

            <form id="lineup-profile-form" className={classes.drawerForm} onSubmit={form.onSubmit(handleSubmit)}>
                {entry && (
                    <SideDrawerSection title={t`At this event`} surface>
                        <div className={classes.fieldStack}>
                            <div>
                                <div className={classes.fieldLabel}>{t`Billing`}</div>
                                <SegmentedControl
                                    fullWidth
                                    disabled={isReadOnly}
                                    aria-label={t`Billing`}
                                    data={BILLING_TIERS.map(tier => ({value: tier, label: billingTierLabel(tier)}))}
                                    {...form.getInputProps('billing_tier')}
                                />
                            </div>
                            <TextInput
                                label={t`Role`}
                                placeholder={t`e.g. Keynote, Closing set, Moderator`}
                                disabled={isReadOnly}
                                maxLength={100}
                                {...form.getInputProps('role_label')}
                            />
                            <Switch
                                label={t`Hide from event page`}
                                description={t`Keep them off the public lineup and schedule until you're ready to announce`}
                                disabled={isReadOnly}
                                {...form.getInputProps('is_hidden', {type: 'checkbox'})}
                            />
                        </div>
                    </SideDrawerSection>
                )}

                <SideDrawerSection title={t`Profile`}>
                    {!canEditProfile && !isReadOnly && (
                        <Alert variant="light" color="gray" icon={<IconInfoCircle size={16}/>} mb="md">
                            {t`Profiles belong to the organizer. You need access to the organizer to edit them.`}
                        </Alert>
                    )}
                    <div className={classes.fieldStack}>
                        <TextInput
                            label={t`Name`}
                            required
                            disabled={fieldsDisabled}
                            maxLength={255}
                            data-autofocus={!profile || undefined}
                            {...form.getInputProps('name')}
                        />
                        <TextInput
                            label={t`Tagline`}
                            placeholder={t`e.g. Founder at Acme, or Berlin techno`}
                            disabled={fieldsDisabled}
                            maxLength={255}
                            {...form.getInputProps('tagline')}
                        />
                        {fieldsDisabled
                            ? profile?.bio && <UserGeneratedContent className={classes.readOnlyContent} html={profile.bio}/>
                            : (
                                <Editor
                                    label={t`Bio`}
                                    editorType="simple"
                                    value={form.values.bio}
                                    onChange={value => form.setFieldValue('bio', value)}
                                    maxLength={20000}
                                />
                            )}
                    </div>
                </SideDrawerSection>

                {!entry && profile?.appearances && profile.appearances.length > 0 && (
                    <SideDrawerSection title={t`Appears on`} count={profile.appearances.length}>
                        <ul className={classes.appearanceList}>
                            {profile.appearances.map(appearance => (
                                <li key={appearance.event_id}>
                                    <NavLink to={`/manage/event/${appearance.event_id}/lineup`} className={classes.appearanceLink}>
                                        <span className={classes.entryText}>
                                            <span className={classes.entryName}>{appearance.event_title}</span>
                                            <span className={classes.entryTagline}>
                                                {[billingTierLabel(appearance.billing_tier), appearance.role_label].filter(Boolean).join(' · ')}
                                            </span>
                                        </span>
                                        {appearance.is_hidden && <Badge size="xs" variant="light" color="orange">{t`Hidden`}</Badge>}
                                        <EventStatusBadge status={appearance.event_status}/>
                                    </NavLink>
                                </li>
                            ))}
                        </ul>
                    </SideDrawerSection>
                )}

                <SideDrawerSection title={t`Photos`}>
                    {profile
                        ? <ProfileImagesField profile={profile} organizerId={organizerId} disabled={fieldsDisabled}/>
                        : <p className={classes.fieldHelp}>{t`Create the profile first, then add photos here.`}</p>}
                </SideDrawerSection>

                <SideDrawerSection title={t`Links`}>
                    <ProfileLinksField
                        value={form.values.links}
                        savedLinks={profile?.links ?? []}
                        onChange={links => form.setFieldValue('links', links)}
                        errors={form.errors}
                        disabled={fieldsDisabled}
                    />
                </SideDrawerSection>
            </form>
        </SideDrawer>
    );
};
