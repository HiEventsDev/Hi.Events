import {useMemo, useRef, useState} from "react";
import {t} from "@lingui/macro";
import {Avatar, Combobox, Loader, TextInput, useCombobox} from "@mantine/core";
import {IconCheck, IconPlus, IconUserPlus} from "@tabler/icons-react";
import {Event} from "../../../../types.ts";
import {EventLineup, LineupEntry, Profile} from "../../../../api/lineup.client.ts";
import {useGetProfiles} from "../../../../queries/useGetProfiles.ts";
import {useUpsertLineupEntry} from "../../../../mutations/useUpsertLineupEntry.ts";
import {useUpsertProfile} from "../../../../mutations/useUpsertProfile.ts";
import {useAccess} from "../../../../hooks/useAccess.ts";
import {initialsFor, profilePhoto} from "../../../../utilites/lineup.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import classes from "./Lineup.module.scss";

const CREATE_OPTION = '__create__';

interface QuickAddPersonProps {
    event: Event;
    lineup: EventLineup;
    onAdded: (entry: LineupEntry, isNewProfile: boolean) => void;
}

export const QuickAddPerson = ({event, lineup, onAdded}: QuickAddPersonProps) => {
    const {canManageOrganizer, isReadOnly} = useAccess();
    const {data: profiles, isLoading} = useGetProfiles(event.organizer_id);
    const entryMutation = useUpsertLineupEntry();
    const profileMutation = useUpsertProfile();
    const [search, setSearch] = useState('');
    const inputRef = useRef<HTMLInputElement>(null);
    const combobox = useCombobox({onDropdownClose: () => combobox.resetSelectedOption()});
    const canCreate = canManageOrganizer(event.organizer_id);
    const isSaving = entryMutation.isPending || profileMutation.isPending;

    const onLineup = useMemo(() => new Set(lineup.entries.map(entry => entry.profile.id)), [lineup.entries]);
    const query = search.trim();

    const matches = useMemo(() => (profiles ?? [])
        .filter(profile => !query
            || profile.name.toLowerCase().includes(query.toLowerCase())
            || profile.tagline?.toLowerCase().includes(query.toLowerCase()))
        .slice(0, 30), [profiles, query]);

    const exactMatch = matches.some(profile => profile.name.toLowerCase() === query.toLowerCase());

    if (isReadOnly) {
        return null;
    }

    const addEntry = async (profile: Profile, isNewProfile: boolean) => {
        const {data: entry} = await entryMutation.mutateAsync({
            eventId: event.id!,
            entry: {profile_id: profile.id, billing_tier: 'STANDARD', role_label: null, is_hidden: false},
        });
        const name = profile.name;
        showSuccess(t`${name} added to the lineup`);
        onAdded(entry, isNewProfile);
    };

    const handleSubmit = async (value: string) => {
        combobox.closeDropdown();
        setSearch('');

        try {
            if (value === CREATE_OPTION) {
                const {data: profile} = await profileMutation.mutateAsync({
                    organizerId: event.organizer_id!,
                    profile: {name: query, tagline: null, bio: null, links: []},
                });
                await addEntry(profile, true);
                return;
            }

            const profile = profiles?.find(candidate => String(candidate.id) === value);
            if (profile && !onLineup.has(profile.id)) {
                await addEntry(profile, false);
            }
        } catch {
            showError(t`Could not add to the lineup. Please try again.`);
        } finally {
            inputRef.current?.focus();
        }
    };

    return (
        <Combobox store={combobox} onOptionSubmit={handleSubmit} withinPortal position="bottom-start">
            <Combobox.Target>
                <TextInput
                    ref={inputRef}
                    className={classes.quickAdd}
                    leftSection={isSaving ? <Loader size={14}/> : <IconUserPlus size={16}/>}
                    placeholder={canCreate ? t`Add a speaker, artist or host…` : t`Add someone from your saved profiles…`}
                    value={search}
                    onChange={changeEvent => {
                        setSearch(changeEvent.currentTarget.value);
                        combobox.openDropdown();
                        combobox.updateSelectedOptionIndex();
                    }}
                    onFocus={() => combobox.openDropdown()}
                    onClick={() => combobox.openDropdown()}
                    onBlur={() => combobox.closeDropdown()}
                    data-testid="lineup-quick-add-input"
                />
            </Combobox.Target>

            <Combobox.Dropdown className={classes.quickAddDropdown}>
                <Combobox.Options mah={340} style={{overflowY: 'auto'}}>
                    {isLoading && <Combobox.Empty><Loader size="xs"/></Combobox.Empty>}

                    {!isLoading && matches.length === 0 && !query && (
                        <Combobox.Empty>{t`Type a name to create a profile`}</Combobox.Empty>
                    )}

                    {matches.length > 0 && (
                        <Combobox.Group label={t`Saved profiles`}>
                            {matches.map(profile => {
                                const added = onLineup.has(profile.id);
                                return (
                                    <Combobox.Option
                                        key={profile.id}
                                        value={String(profile.id)}
                                        disabled={added}
                                        className={classes.quickAddOption}
                                    >
                                        <Avatar src={profilePhoto(profile)?.url} alt="" size={28} radius="sm" color="primary">
                                            {initialsFor(profile.name)}
                                        </Avatar>
                                        <span className={classes.quickAddText}>
                                            <span className={classes.entryName}>{profile.name}</span>
                                            {profile.tagline && <span className={classes.entryTagline}>{profile.tagline}</span>}
                                        </span>
                                        {added && <IconCheck size={14} className={classes.quickAddAdded}/>}
                                    </Combobox.Option>
                                );
                            })}
                        </Combobox.Group>
                    )}

                    {canCreate && query && !exactMatch && (
                        <Combobox.Option value={CREATE_OPTION} className={classes.quickAddOption} data-testid="lineup-quick-add-create">
                            <span className={classes.quickAddCreateIcon}><IconPlus size={14}/></span>
                            <span className={classes.quickAddText}>
                                <span className={classes.entryName}>{t`Create "${query}"`}</span>
                                <span className={classes.entryTagline}>{t`New profile, saved for future events`}</span>
                            </span>
                        </Combobox.Option>
                    )}
                </Combobox.Options>
            </Combobox.Dropdown>
        </Combobox>
    );
};
