import {CSSProperties, useState} from "react";
import {t} from "@lingui/macro";
import {CheckIcon, Combobox, Group, Pill, PillsInput, useCombobox} from "@mantine/core";
import {IconPlus} from "@tabler/icons-react";
import {useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../../types.ts";
import {firstApiError} from "../../../../utilites/apiErrors.ts";
import {showError} from "../../../../utilites/notifications.tsx";
import {useCreateCrmTag} from "../../mutations/useCreateCrmTag.ts";
import {crmKeys} from "../../queries/crmQueryKeys.ts";
import {useGetCrmTags} from "../../queries/useGetCrmTags.ts";
import {CrmTag} from "../../types.ts";
import classes from "./CrmTagSelect.module.scss";

const MAX_TAG_LENGTH = 50;

const dotStyle = (tag: CrmTag): CSSProperties => ({background: tag.color ?? 'var(--mantine-color-gray-5)'});

export const CrmTagSelect = ({organizerId, value, onChange, label, description, maxValues, error}: {
    organizerId: IdParam,
    value: number[],
    onChange: (tagIds: number[]) => void,
    label?: string,
    description?: string,
    maxValues?: number,
    error?: string,
}) => {
    const queryClient = useQueryClient();
    const tagsQuery = useGetCrmTags(organizerId);
    const createTag = useCreateCrmTag();
    const combobox = useCombobox({
        onDropdownClose: () => combobox.resetSelectedOption(),
        onDropdownOpen: () => combobox.updateSelectedOptionIndex('active'),
    });
    const [search, setSearch] = useState('');
    const tags = tagsQuery.data ?? [];
    const byId = new Map(tags.map(tag => [tag.id, tag]));
    const term = search.trim();
    const isFull = maxValues !== undefined && value.length >= maxValues;
    const matches = tags.filter(tag => tag.name.toLowerCase().includes(term.toLowerCase()));
    const exact = tags.find(tag => tag.name.toLowerCase() === term.toLowerCase());
    const canCreate = term !== '' && !exact && !isFull;

    const toggle = (tagId: number) => {
        if (value.includes(tagId)) {
            onChange(value.filter(id => id !== tagId));
        } else if (!isFull) {
            onChange([...value, tagId]);
        }
        setSearch('');
    };

    const create = () => createTag.mutate({organizerId, tag: {name: term, color: null}}, {
        onSuccess: ({data}) => {
            queryClient.setQueryData<CrmTag[]>(crmKeys.tags(organizerId), current => current?.some(tag => tag.id === data.id) ? current : [...(current ?? []), data]);
            onChange([...value, data.id]);
            setSearch('');
        },
        onError: createError => showError(firstApiError(createError, t`We couldn't create that tag. Please try again.`)),
    });

    const handleSubmit = (option: string) => {
        if (option === '$create') {
            create();
            return;
        }
        toggle(Number(option));
    };

    return (
        <Combobox store={combobox} onOptionSubmit={handleSubmit} withinPortal>
            <Combobox.DropdownTarget>
                <PillsInput
                    label={label}
                    description={description}
                    error={error}
                    onClick={() => combobox.openDropdown()}
                >
                    <Pill.Group>
                        {value.map(tagId => {
                            const tag = byId.get(tagId);
                            if (!tag) {
                                return null;
                            }
                            return (
                                <Pill key={tagId} withRemoveButton onRemove={() => toggle(tagId)} className={classes.pill}>
                                    <span className={classes.dot} style={dotStyle(tag)}/>
                                    {tag.name}
                                </Pill>
                            );
                        })}
                        <Combobox.EventsTarget withExpandedAttribute>
                            <PillsInput.Field
                                value={search}
                                placeholder={value.length === 0 ? t`Find or create a tag` : undefined}
                                aria-label={label ?? t`Tags`}
                                maxLength={MAX_TAG_LENGTH}
                                onFocus={() => combobox.openDropdown()}
                                onBlur={() => combobox.closeDropdown()}
                                onChange={event => {
                                    combobox.updateSelectedOptionIndex();
                                    setSearch(event.currentTarget.value);
                                }}
                                onKeyDown={event => {
                                    if (event.key === 'Enter' && term !== '' && combobox.getSelectedOptionIndex() === -1) {
                                        event.preventDefault();
                                        if (canCreate) {
                                            create();
                                        } else if (exact ?? matches.length === 1) {
                                            const match = exact ?? matches[0];
                                            if (value.includes(match.id)) {
                                                setSearch('');
                                            } else {
                                                toggle(match.id);
                                            }
                                        }
                                        return;
                                    }
                                    if (event.key === 'Backspace' && search === '' && value.length > 0) {
                                        event.preventDefault();
                                        onChange(value.slice(0, -1));
                                    }
                                }}
                            />
                        </Combobox.EventsTarget>
                    </Pill.Group>
                </PillsInput>
            </Combobox.DropdownTarget>

            <Combobox.Dropdown>
                <Combobox.Options mah={240} style={{overflowY: 'auto'}}>
                    {matches.map(tag => {
                        const isSelected = value.includes(tag.id);
                        return (
                            <Combobox.Option value={String(tag.id)} key={tag.id} active={isSelected} disabled={!isSelected && isFull}>
                                <Group gap={8} wrap="nowrap">
                                    <span className={classes.check}>{isSelected && <CheckIcon size={12}/>}</span>
                                    <span className={classes.dot} style={dotStyle(tag)}/>
                                    <span className={classes.name}>{tag.name}</span>
                                </Group>
                            </Combobox.Option>
                        );
                    })}
                    {canCreate && (
                        <Combobox.Option value="$create" disabled={createTag.isPending}>
                            <Group gap={8} wrap="nowrap">
                                <IconPlus size={14}/>
                                <span className={classes.name}>{t`Create "${term}"`}</span>
                            </Group>
                        </Combobox.Option>
                    )}
                    {matches.length === 0 && !canCreate && (
                        <Combobox.Empty>
                            {isFull ? t`You've reached the tag limit` : term ? t`No tags match` : t`No tags yet. Type a name to create one.`}
                        </Combobox.Empty>
                    )}
                </Combobox.Options>
            </Combobox.Dropdown>
        </Combobox>
    );
};
