import {useMemo, useState} from "react";
import {useParams} from "react-router";
import {t} from "@lingui/macro";
import {Avatar, Skeleton, TextInput, Tooltip} from "@mantine/core";
import {IconChevronRight, IconMicrophone2, IconPhotoOff, IconPlus, IconSearch} from "@tabler/icons-react";
import {PageBody} from "../../../common/PageBody";
import {PageTitle} from "../../../common/PageTitle";
import {CreateButton} from "../../../common/CreateButton";
import {ProfileLinkIcon, profileLinkLabel} from "../../../common/ProfileLinkIcon";
import {useGetProfiles} from "../../../../queries/useGetProfiles.ts";
import {useAccess} from "../../../../hooks/useAccess.ts";
import {Profile, ProfileAppearance} from "../../../../api/lineup.client.ts";
import {initialsFor, profilePhoto} from "../../../../utilites/lineup.ts";
import {ProfileDrawer} from "../../event/Lineup/ProfileDrawer.tsx";
import lineupClasses from "../../event/Lineup/Lineup.module.scss";
import classes from "./People.module.scss";

type DrawerTarget = { profileId: number } | 'new';
type Filter = 'all' | 'missing_photo' | 'unused' | 'live';

const isPublicAppearance = (appearance: ProfileAppearance) => appearance.event_status === 'LIVE' && !appearance.is_hidden;

const isOnLiveEvent = (profile: Profile) => (profile.appearances ?? []).some(isPublicAppearance);

const People = () => {
    const {organizerId} = useParams();
    const {canManageOrganizer, isReadOnly} = useAccess();
    const {data: profiles, isLoading} = useGetProfiles(organizerId);
    const [search, setSearch] = useState('');
    const [filter, setFilter] = useState<Filter>('all');
    const [drawer, setDrawer] = useState<DrawerTarget | null>(null);
    const canCreate = canManageOrganizer(organizerId) && !isReadOnly;

    const counts = useMemo(() => {
        const all = profiles ?? [];
        return {
            all: all.length,
            missing_photo: all.filter(profile => profile.images.length === 0).length,
            unused: all.filter(profile => (profile.appearances ?? []).length === 0).length,
            live: all.filter(isOnLiveEvent).length,
        };
    }, [profiles]);

    const filtered = useMemo(() => {
        const query = search.trim().toLowerCase();
        return (profiles ?? [])
            .filter(profile => !query
                || profile.name.toLowerCase().includes(query)
                || profile.tagline?.toLowerCase().includes(query))
            .filter(profile => {
                switch (filter) {
                    case 'missing_photo':
                        return profile.images.length === 0;
                    case 'unused':
                        return (profile.appearances ?? []).length === 0;
                    case 'live':
                        return isOnLiveEvent(profile);
                    default:
                        return true;
                }
            });
    }, [profiles, search, filter]);

    const drawerProfile = drawer && drawer !== 'new'
        ? profiles?.find(profile => profile.id === drawer.profileId)
        : undefined;

    const stats = [
        {key: 'all' as Filter, label: t`Profiles`, value: counts.all, hint: t`Saved for this organizer`},
        {key: 'live' as Filter, label: t`On live events`, value: counts.live, hint: t`Showing on a public event page`},
        {key: 'missing_photo' as Filter, label: t`Missing a photo`, value: counts.missing_photo, hint: t`Show initials instead`, warn: counts.missing_photo > 0},
        {key: 'unused' as Filter, label: t`Not on any event`, value: counts.unused, hint: t`Ready to add to a lineup`},
    ];

    return (
        <PageBody>
            <div className={lineupClasses.page}>
                <div className={lineupClasses.pageHeader}>
                    <PageTitle subheading={t`Speakers, artists and hosts you can add to any of this organizer's events. Edit a profile once and every event shows the change.`}>
                        {t`People`}
                    </PageTitle>
                    {canCreate && (
                        <div className={lineupClasses.pageActions}>
                            <CreateButton leftSection={<IconPlus size={16}/>} onClick={() => setDrawer('new')} data-testid="people-create-button">
                                {t`New profile`}
                            </CreateButton>
                        </div>
                    )}
                </div>

                <div className={lineupClasses.stats}>
                    {stats.map(stat => (
                        <button
                            key={stat.key}
                            type="button"
                            className={`${lineupClasses.stat} ${classes.statButton}`}
                            data-active={filter === stat.key || undefined}
                            onClick={() => setFilter(stat.key)}
                        >
                            <span className={lineupClasses.statLabel}>{stat.label}</span>
                            <span className={lineupClasses.statValue}>{isLoading ? '–' : stat.value}</span>
                            <span className={lineupClasses.statHint} data-warn={stat.warn || undefined}>{stat.hint}</span>
                        </button>
                    ))}
                </div>

                <div className={classes.toolbar}>
                    <TextInput
                        className={classes.search}
                        leftSection={<IconSearch size={16}/>}
                        placeholder={t`Search by name or tagline`}
                        value={search}
                        onChange={event => setSearch(event.currentTarget.value)}
                    />
                </div>

                <div className={classes.table}>
                    <div className={classes.tableHead}>
                        <span>{t`Name`}</span>
                        <span>{t`Appears on`}</span>
                        <span>{t`Links`}</span>
                        <span/>
                    </div>

                    {isLoading && Array.from({length: 5}).map((_, index) => (
                        <div key={index} className={classes.row}><Skeleton height={36} radius="md"/></div>
                    ))}

                    {!isLoading && filtered.map(profile => (
                        <PersonRow key={profile.id} profile={profile} onOpen={() => setDrawer({profileId: profile.id})}/>
                    ))}

                    {!isLoading && filtered.length === 0 && (
                        <div className={lineupClasses.panelEmpty}>
                            <IconMicrophone2 size={22} stroke={1.5}/>
                            <p>
                                {counts.all === 0
                                    ? t`No profiles yet. Create one here, or add people straight from an event's Lineup & Schedule page.`
                                    : t`Nobody matches these filters.`}
                            </p>
                        </div>
                    )}
                </div>

                {drawer && (drawer === 'new' || drawerProfile) && (
                    <ProfileDrawer
                        organizerId={organizerId!}
                        profile={drawerProfile}
                        onCreated={profile => setDrawer({profileId: profile.id})}
                        onClose={() => setDrawer(null)}
                    />
                )}
            </div>
        </PageBody>
    );
};

const PersonRow = ({profile, onOpen}: { profile: Profile, onOpen: () => void }) => {
    const photo = profilePhoto(profile);
    const appearances = profile.appearances ?? [];
    const extra = appearances.length - 2;

    return (
        <button type="button" className={classes.row} onClick={onOpen} data-testid={`people-row-${profile.id}`}>
            <span className={classes.identity}>
                <Avatar src={photo?.url} alt="" size={40} radius="md" color="primary">
                    {initialsFor(profile.name)}
                </Avatar>
                <span className={lineupClasses.entryText}>
                    <span className={lineupClasses.entryName}>{profile.name}</span>
                    <span className={lineupClasses.entryTagline}>
                        {profile.tagline || (photo ? '' : <span className={classes.noPhoto}><IconPhotoOff size={11}/>{t`No photo`}</span>)}
                    </span>
                </span>
            </span>

            <span className={classes.appearances}>
                {appearances.length === 0 && <span className={classes.muted}>{t`Not on any event`}</span>}
                {appearances.slice(0, 2).map(appearance => (
                    <span key={appearance.event_id} className={classes.eventChip} data-live={isPublicAppearance(appearance) || undefined}>
                        <span className={classes.eventDot}/>
                        <span className={classes.eventChipText}>{appearance.event_title}</span>
                    </span>
                ))}
                {extra > 0 && <span className={classes.muted}>{t`+${extra} more`}</span>}
            </span>

            <span className={classes.links}>
                {profile.links.slice(0, 4).map(link => (
                    <Tooltip key={link.url} label={profileLinkLabel(link.type)}>
                        <span><ProfileLinkIcon type={link.type} size={15}/></span>
                    </Tooltip>
                ))}
            </span>

            <IconChevronRight size={16} className={classes.chevron}/>
        </button>
    );
};

export default People;
