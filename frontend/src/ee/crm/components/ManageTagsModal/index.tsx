import {useState} from "react";
import {plural, t} from "@lingui/macro";
import {ActionIcon, Button, CheckIcon, ColorSwatch, Popover, Skeleton, TextInput, Tooltip, UnstyledButton} from "@mantine/core";
import {useForm} from "@mantine/form";
import {IconPlus, IconTrash} from "@tabler/icons-react";
import {Modal, ModalFooter} from "../../../../components/common/Modal";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.tsx";
import {IdParam} from "../../../../types.ts";
import {firstApiError} from "../../../../utilites/apiErrors.ts";
import {confirmationDialog} from "../../../../utilites/confirmationDialog.tsx";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {useCrmAccess} from "../../hooks/useCrmAccess.ts";
import {useCreateCrmTag} from "../../mutations/useCreateCrmTag.ts";
import {useDeleteCrmTag} from "../../mutations/useDeleteCrmTag.ts";
import {useUpdateCrmTag} from "../../mutations/useUpdateCrmTag.ts";
import {useGetCrmTags} from "../../queries/useGetCrmTags.ts";
import {CrmTag} from "../../types.ts";
import {LoadError} from "../LoadError";
import shared from "../Audience.module.scss";
import classes from "./ManageTagsModal.module.scss";

interface ManageTagsModalProps {
    organizerId: IdParam;
    onClose: () => void;
}

const NO_COLOR = 'var(--mantine-color-gray-4)';

const tagColors = () => [
    {value: '#E03131', label: t`Red`},
    {value: '#F08C00', label: t`Orange`},
    {value: '#2F9E44', label: t`Green`},
    {value: '#1971C2', label: t`Blue`},
    {value: '#7048E8', label: t`Purple`},
    {value: '#C2255C', label: t`Pink`},
];

const validateName = (value: string) => value.trim() ? null : t`Give this tag a name`;

const TagColorPicker = ({value, onChange, disabled}: {
    value: string | null,
    onChange: (color: string | null) => void,
    disabled: boolean,
}) => {
    const [opened, setOpened] = useState(false);
    const current = tagColors().find(color => color.value.toLowerCase() === value?.toLowerCase());
    const currentLabel = current?.label ?? t`No colour`;

    const pick = (color: string | null) => {
        onChange(color);
        setOpened(false);
    };

    return (
        <Popover opened={opened} onChange={setOpened} position="bottom-start" shadow="md" trapFocus withinPortal>
            <Popover.Target>
                <UnstyledButton
                    className={classes.colorButton}
                    aria-label={t`Tag colour: ${currentLabel}`}
                    disabled={disabled}
                    onClick={() => setOpened(isOpen => !isOpen)}
                >
                    <ColorSwatch size={16} color={value ?? NO_COLOR} withShadow={false}/>
                </UnstyledButton>
            </Popover.Target>
            <Popover.Dropdown className={classes.colorOptions} role="group" aria-label={t`Tag colour`}>
                <ColorSwatch
                    component="button"
                    type="button"
                    size={26}
                    color={NO_COLOR}
                    aria-label={t`No colour`}
                    aria-pressed={value === null}
                    onClick={() => pick(null)}
                >
                    {value === null && <CheckIcon size={10}/>}
                </ColorSwatch>
                {tagColors().map(color => (
                    <ColorSwatch
                        key={color.value}
                        component="button"
                        type="button"
                        size={26}
                        color={color.value}
                        c="white"
                        aria-label={color.label}
                        aria-pressed={color === current}
                        onClick={() => pick(color.value)}
                    >
                        {color === current && <CheckIcon size={10}/>}
                    </ColorSwatch>
                ))}
            </Popover.Dropdown>
        </Popover>
    );
};

const NewTagRow = ({organizerId}: { organizerId: IdParam }) => {
    const createTag = useCreateCrmTag();
    const errorHandler = useFormErrorResponseHandler();
    const form = useForm({
        initialValues: {name: '', color: null as string | null},
        validate: {name: validateName},
    });

    const handleSubmit = (values: typeof form.values) => createTag.mutate({
        organizerId,
        tag: {name: values.name.trim(), color: values.color},
    }, {
        onSuccess: () => {
            form.reset();
            showSuccess(t`Tag created`);
        },
        onError: error => errorHandler(form, error),
    });

    return (
        <form className={classes.tagRow} onSubmit={form.onSubmit(handleSubmit)} data-new>
            <TagColorPicker value={form.values.color} onChange={color => form.setFieldValue('color', color)} disabled={false}/>
            <TextInput
                {...form.getInputProps('name')}
                className={classes.tagRowName}
                aria-label={t`New tag name`}
                placeholder={t`New tag name`}
                maxLength={50}
            />
            <Button
                type="submit"
                variant="default"
                leftSection={<IconPlus size={14}/>}
                loading={createTag.isPending}
            >
                {t`Add tag`}
            </Button>
        </form>
    );
};

const TagRow = ({organizerId, tag, canChange}: { organizerId: IdParam, tag: CrmTag, canChange: boolean }) => {
    const updateTag = useUpdateCrmTag();
    const deleteTag = useDeleteCrmTag();
    const errorHandler = useFormErrorResponseHandler();
    const form = useForm({
        initialValues: {name: tag.name, color: tag.color},
        validate: {name: validateName},
    });

    const handleSubmit = (values: typeof form.values) => updateTag.mutate({
        organizerId,
        tagId: tag.id,
        tag: {name: values.name.trim(), color: values.color},
    }, {
        onSuccess: () => {
            form.resetDirty(values);
            showSuccess(t`Tag updated`);
        },
        onError: error => errorHandler(form, error),
    });

    const tagName = tag.name;

    const handleDelete = () => confirmationDialog(
        t`Delete the tag "${tagName}"?`,
        () => deleteTag.mutateAsync({organizerId, tagId: tag.id})
            .then(() => showSuccess(t`Tag deleted`))
            .catch(error => showError(firstApiError(error, t`We couldn't delete this tag. Please try again.`))),
        {
            confirm: t`Delete tag`,
            destructive: true,
            description: t`It will be removed from every contact that has it.`,
        },
    );

    return (
        <form className={classes.tagRow} onSubmit={form.onSubmit(handleSubmit)}>
            <TagColorPicker value={form.values.color} onChange={color => form.setFieldValue('color', color)} disabled={!canChange}/>
            <TextInput
                {...form.getInputProps('name')}
                className={classes.tagRowName}
                aria-label={t`Tag name`}
                maxLength={50}
                disabled={!canChange}
            />
            <span className={classes.tagRowCount}>
                {plural(tag.contacts_count ?? 0, {one: '# contact', other: '# contacts'})}
            </span>
            <div className={classes.tagRowActions}>
                {canChange && form.isDirty() && (
                    <Button type="submit" size="xs" loading={updateTag.isPending}>{t`Save`}</Button>
                )}
                <Tooltip label={t`Delete tag`}>
                    <ActionIcon
                        className={classes.touchTarget}
                        variant="subtle"
                        color="red"
                        aria-label={t`Delete tag`}
                        loading={deleteTag.isPending}
                        onClick={handleDelete}
                    >
                        <IconTrash size={16}/>
                    </ActionIcon>
                </Tooltip>
            </div>
        </form>
    );
};

export const ManageTagsModal = ({organizerId, onClose}: ManageTagsModalProps) => {
    const {canChange} = useCrmAccess();
    const tagsQuery = useGetCrmTags(organizerId);
    const tags = tagsQuery.data ?? [];

    return (
        <Modal opened onClose={onClose} heading={t`Manage tags`} size="lg">
            {canChange && <NewTagRow organizerId={organizerId}/>}

            {tagsQuery.isLoading && <Skeleton height={120} radius="md"/>}

            {tagsQuery.isError && (
                <LoadError message={t`We couldn't load your tags.`} onRetry={() => tagsQuery.refetch()}/>
            )}

            {tagsQuery.data && tags.length === 0 && (
                <div className={shared.empty}>
                    <p>{canChange ? t`No tags yet. Add your first one above.` : t`No tags yet.`}</p>
                </div>
            )}

            {tags.map(tag => <TagRow key={tag.id} organizerId={organizerId} tag={tag} canChange={canChange}/>)}

            <ModalFooter>
                <Button variant="default" onClick={onClose}>{t`Close`}</Button>
            </ModalFooter>
        </Modal>
    );
};
