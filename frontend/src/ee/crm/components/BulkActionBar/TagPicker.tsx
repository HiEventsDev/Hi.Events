import {useState} from "react";
import {t} from "@lingui/macro";
import {Button, Popover, ScrollArea, TextInput, Tooltip, UnstyledButton} from "@mantine/core";
import {IconPlus, IconSearch} from "@tabler/icons-react";
import {IdParam} from "../../../../types.ts";
import {firstApiError} from "../../../../utilites/apiErrors.ts";
import {showError} from "../../../../utilites/notifications.tsx";
import {useCreateCrmTag} from "../../mutations/useCreateCrmTag.ts";
import {useGetCrmTags} from "../../queries/useGetCrmTags.ts";
import {CrmTag} from "../../types.ts";
import classes from "./BulkActionBar.module.scss";

export const TagPicker = ({organizerId, label, icon, disabledReason, allowCreate, onPick}: {
    organizerId: IdParam,
    label: string,
    icon: React.ReactNode,
    disabledReason: string | null,
    allowCreate: boolean,
    onPick: (tag: CrmTag) => void,
}) => {
    const [opened, setOpened] = useState(false);
    const [search, setSearch] = useState('');
    const tagsQuery = useGetCrmTags(organizerId);
    const createTag = useCreateCrmTag();
    const term = search.trim();
    const tags = (tagsQuery.data ?? []).filter(tag => tag.name.toLowerCase().includes(term.toLowerCase()));
    const canCreate = allowCreate && term !== '' && !(tagsQuery.data ?? []).some(tag => tag.name.toLowerCase() === term.toLowerCase());

    const pick = (tag: CrmTag) => {
        setOpened(false);
        setSearch('');
        onPick(tag);
    };

    const create = () => createTag.mutate({organizerId, tag: {name: term, color: null}}, {
        onSuccess: ({data}) => pick(data),
        onError: error => showError(firstApiError(error, t`We couldn't create that tag. Please try again.`)),
    });

    const trigger = (
        <Button
            variant="subtle"
            color="gray"
            size="compact-sm"
            leftSection={icon}
            disabled={disabledReason !== null}
            onClick={() => setOpened(value => !value)}
        >
            {label}
        </Button>
    );

    if (disabledReason !== null) {
        return <Tooltip label={disabledReason}><span>{trigger}</span></Tooltip>;
    }

    return (
        <Popover opened={opened} onChange={setOpened} position="top" width={260} shadow="md" trapFocus>
            <Popover.Target>{trigger}</Popover.Target>
            <Popover.Dropdown className={classes.tagDropdown}>
                <TextInput
                    size="xs"
                    leftSection={<IconSearch size={14}/>}
                    placeholder={allowCreate ? t`Find or create a tag` : t`Find a tag`}
                    value={search}
                    onChange={event => setSearch(event.currentTarget.value)}
                    onKeyDown={event => {
                        if (event.key === 'Enter' && tags.length === 1) {
                            pick(tags[0]);
                        } else if (event.key === 'Enter' && canCreate) {
                            create();
                        }
                    }}
                    maxLength={50}
                    data-autofocus
                />
                <ScrollArea.Autosize mah={220} type="auto">
                    {tags.map(tag => (
                        <UnstyledButton key={tag.id} className={classes.tagOption} onClick={() => pick(tag)}>
                            <span className={classes.tagDot} style={{background: tag.color ?? 'var(--mantine-color-gray-5)'}}/>
                            {tag.name}
                        </UnstyledButton>
                    ))}
                    {tagsQuery.isLoading && <p className={classes.tagEmpty}>{t`Loading…`}</p>}
                    {tagsQuery.isError && <p className={classes.tagEmpty}>{t`We couldn't load your tags. Please refresh the page.`}</p>}
                    {tagsQuery.data && tags.length === 0 && !canCreate && (
                        <p className={classes.tagEmpty}>{term ? t`No tags match` : t`No tags yet`}</p>
                    )}
                </ScrollArea.Autosize>
                {canCreate && (
                    <UnstyledButton className={classes.tagOption} onClick={create} disabled={createTag.isPending}>
                        <IconPlus size={14}/>{t`Create "${term}"`}
                    </UnstyledButton>
                )}
            </Popover.Dropdown>
        </Popover>
    );
};
