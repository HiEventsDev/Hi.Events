import {
    ActionIcon,
    Badge,
    Button,
    Container,
    Group,
    Menu,
    Pagination,
    SegmentedControl,
    Select,
    Skeleton,
    Stack,
    Table,
    Text,
    TextInput,
    Title,
    Tooltip,
} from "@mantine/core";
import {t} from "@lingui/macro";
import {
    IconCopy,
    IconDotsVertical,
    IconEdit,
    IconEye,
    IconPlus,
    IconSearch,
    IconSpeakerphone,
    IconTrash,
} from "@tabler/icons-react";
import {useEffect, useState} from "react";
import {useForm} from "@mantine/form";
import {useGetAdminAnnouncements} from "../../../../queries/useGetAdminAnnouncements";
import {useCreateAnnouncement} from "../../../../mutations/useCreateAnnouncement";
import {useUpdateAnnouncement} from "../../../../mutations/useUpdateAnnouncement";
import {useDeleteAnnouncement} from "../../../../mutations/useDeleteAnnouncement";
import {
    AdminAnnouncement,
    AnnouncementDisplayType,
    AnnouncementStatus,
    AnnouncementTargetType,
    UpsertAnnouncementData,
} from "../../../../api/announcement.client";
import {Modal} from "../../../common/Modal";
import {Editor} from "../../../common/Editor";
import {BouncingEmoji} from "../../../common/BouncingEmoji";
import {AnnouncementBanner} from "../../../common/AnnouncementDisplay/AnnouncementBanner";
import {AnnouncementModal} from "../../../common/AnnouncementDisplay/AnnouncementModal";
import {AnnouncementTargetPicker} from "./AnnouncementTargetPicker";
import {showError, showSuccess} from "../../../../utilites/notifications";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler";
import tableStyles from "../../../../styles/admin-table.module.scss";
import classes from "./Announcements.module.scss";

interface AnnouncementFormValues {
    title: string;
    content: string;
    display_type: AnnouncementDisplayType;
    emoji: string;
    target_type: AnnouncementTargetType;
    target_account_ids: number[];
    target_user_ids: number[];
    cta_label: string;
    cta_url: string;
    status: AnnouncementStatus;
}

const targetSummary = (announcement: AdminAnnouncement) => {
    const names = Object.values(announcement.target_names || {});

    if (announcement.target_type === 'ACCOUNTS') {
        return {label: t`Accounts (${names.length})`, tooltip: names.join(', ')};
    }
    if (announcement.target_type === 'USERS') {
        return {label: t`Users (${names.length})`, tooltip: names.join(', ')};
    }
    return {label: t`All users`, tooltip: undefined};
};

const Announcements = () => {
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState("");
    const [debouncedSearch, setDebouncedSearch] = useState("");
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [editingAnnouncement, setEditingAnnouncement] = useState<AdminAnnouncement | null>(null);
    const [duplicatingAnnouncement, setDuplicatingAnnouncement] = useState<AdminAnnouncement | null>(null);

    const {data: announcementsData, isLoading} = useGetAdminAnnouncements({
        page,
        per_page: 20,
        search: debouncedSearch,
    });
    const deleteMutation = useDeleteAnnouncement();

    useEffect(() => {
        const timer = setTimeout(() => {
            setDebouncedSearch(search);
            setPage(1);
        }, 500);

        return () => clearTimeout(timer);
    }, [search]);

    const announcements = announcementsData?.data || [];

    const handleDelete = (announcement: AdminAnnouncement) => {
        if (window.confirm(t`Are you sure you want to delete this announcement? Users will no longer see it.`)) {
            deleteMutation.mutate(announcement.id, {
                onSuccess: () => showSuccess(t`Announcement deleted`),
                onError: () => showError(t`Failed to delete announcement`),
            });
        }
    };

    return (
        <>
            <Container size="xl" p="xl">
                <Stack gap="lg">
                    <Group justify="space-between">
                        <Title order={1}>{t`Announcements`}</Title>
                        <Button
                            leftSection={<IconPlus size={16}/>}
                            onClick={() => setShowCreateModal(true)}
                            data-testid="announcement-create-button"
                        >
                            {t`Create Announcement`}
                        </Button>
                    </Group>

                    <TextInput
                        placeholder={t`Search by title...`}
                        leftSection={<IconSearch size={16}/>}
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />

                    {isLoading ? (
                        <Stack gap="md">
                            <Skeleton height={120} radius="md"/>
                            <Skeleton height={120} radius="md"/>
                        </Stack>
                    ) : announcements.length === 0 ? (
                        <div className={tableStyles.emptyState}>
                            <Stack align="center" gap="sm">
                                <IconSpeakerphone size={40} color="var(--hi-color-gray-dark)"/>
                                <Text size="lg" c="dimmed">{t`No announcements yet`}</Text>
                                <Text size="sm" c="dimmed">
                                    {t`Create an announcement to show a message to your users.`}
                                </Text>
                            </Stack>
                        </div>
                    ) : (
                        <div className={tableStyles.tableWrapper}>
                            <div className={tableStyles.tableScroll}>
                                <Table className={`${tableStyles.table} ${classes.announcementsTable}`} highlightOnHover>
                                    <Table.Thead>
                                        <Table.Tr>
                                            <Table.Th>{t`Title`}</Table.Th>
                                            <Table.Th>{t`Type`}</Table.Th>
                                            <Table.Th>{t`Status`}</Table.Th>
                                            <Table.Th>{t`Audience`}</Table.Th>
                                            <Table.Th>{t`Seen`}</Table.Th>
                                            <Table.Th>{t`Dismissed`}</Table.Th>
                                            <Table.Th>{t`Updated`}</Table.Th>
                                            <Table.Th></Table.Th>
                                        </Table.Tr>
                                    </Table.Thead>
                                    <Table.Tbody>
                                        {announcements.map((announcement) => {
                                            const target = targetSummary(announcement);
                                            const isDraft = announcement.status === 'DRAFT';
                                            return (
                                                <Table.Tr key={announcement.id}>
                                                    <Table.Td>
                                                        <Text fw={500} size="sm">{announcement.title}</Text>
                                                    </Table.Td>
                                                    <Table.Td>
                                                        <Badge variant="light" color={announcement.display_type === 'MODAL' ? 'grape' : 'blue'} size="sm">
                                                            {announcement.display_type === 'MODAL' ? t`Modal` : t`Banner`}
                                                        </Badge>
                                                    </Table.Td>
                                                    <Table.Td>
                                                        <Badge variant="light" color={isDraft ? 'yellow' : 'green'} size="sm">
                                                            {isDraft ? t`Draft` : t`Published`}
                                                        </Badge>
                                                    </Table.Td>
                                                    <Table.Td>
                                                        <Tooltip label={target.tooltip} disabled={!target.tooltip} multiline maw={320}>
                                                            <Badge variant="outline" color="gray" size="sm">{target.label}</Badge>
                                                        </Tooltip>
                                                    </Table.Td>
                                                    <Table.Td>{isDraft ? '—' : announcement.seen_count}</Table.Td>
                                                    <Table.Td>{isDraft ? '—' : announcement.dismissed_count}</Table.Td>
                                                    <Table.Td>
                                                        <Text size="sm" c="dimmed">
                                                            {new Date(announcement.updated_at).toLocaleDateString()}
                                                        </Text>
                                                    </Table.Td>
                                                    <Table.Td>
                                                        <Menu position="bottom-end" withinPortal>
                                                            <Menu.Target>
                                                                <ActionIcon variant="subtle" color="gray" data-testid="announcement-row-menu">
                                                                    <IconDotsVertical size={16}/>
                                                                </ActionIcon>
                                                            </Menu.Target>
                                                            <Menu.Dropdown>
                                                                <Menu.Item
                                                                    leftSection={<IconEdit size={14}/>}
                                                                    onClick={() => setEditingAnnouncement(announcement)}
                                                                    data-testid="announcement-edit-menu-item"
                                                                >
                                                                    {t`Edit`}
                                                                </Menu.Item>
                                                                <Menu.Item
                                                                    leftSection={<IconCopy size={14}/>}
                                                                    onClick={() => setDuplicatingAnnouncement(announcement)}
                                                                    data-testid="announcement-duplicate-menu-item"
                                                                >
                                                                    {t`Duplicate`}
                                                                </Menu.Item>
                                                                <Menu.Item
                                                                    color="red"
                                                                    leftSection={<IconTrash size={14}/>}
                                                                    onClick={() => handleDelete(announcement)}
                                                                    data-testid="announcement-delete-menu-item"
                                                                >
                                                                    {t`Delete`}
                                                                </Menu.Item>
                                                            </Menu.Dropdown>
                                                        </Menu>
                                                    </Table.Td>
                                                </Table.Tr>
                                            );
                                        })}
                                    </Table.Tbody>
                                </Table>
                            </div>
                        </div>
                    )}

                    {announcementsData?.meta && announcementsData.meta.last_page > 1 && (
                        <Pagination
                            total={announcementsData.meta.last_page}
                            value={page}
                            onChange={setPage}
                        />
                    )}
                </Stack>
            </Container>

            {showCreateModal && (
                <AnnouncementFormModal onClose={() => setShowCreateModal(false)}/>
            )}

            {editingAnnouncement && (
                <AnnouncementFormModal
                    announcement={editingAnnouncement}
                    onClose={() => setEditingAnnouncement(null)}
                />
            )}

            {duplicatingAnnouncement && (
                <AnnouncementFormModal
                    announcement={duplicatingAnnouncement}
                    duplicate
                    onClose={() => setDuplicatingAnnouncement(null)}
                />
            )}
        </>
    );
};

interface AnnouncementFormModalProps {
    announcement?: AdminAnnouncement;
    duplicate?: boolean;
    onClose: () => void;
}

const AnnouncementFormModal = ({announcement, duplicate, onClose}: AnnouncementFormModalProps) => {
    const createMutation = useCreateAnnouncement();
    const updateMutation = useUpdateAnnouncement();
    const formErrorHandler = useFormErrorResponseHandler();
    const [previewing, setPreviewing] = useState(false);
    const isEditing = !!announcement && !duplicate;

    const form = useForm<AnnouncementFormValues>({
        initialValues: {
            title: (duplicate ? t`${announcement?.title} (copy)` : announcement?.title) || '',
            content: announcement?.content || '',
            display_type: announcement?.display_type || 'BANNER',
            emoji: announcement?.emoji || '🎉',
            target_type: announcement?.target_type || 'ALL',
            target_account_ids: announcement?.target_account_ids || [],
            target_user_ids: announcement?.target_user_ids || [],
            cta_label: announcement?.cta_label || '',
            cta_url: announcement?.cta_url || '',
            status: (duplicate ? 'DRAFT' : announcement?.status) || 'DRAFT',
        },
        validate: {
            title: (value) => value.trim() ? null : t`Title is required`,
            content: (value, values) => {
                if (!value.trim()) return t`Content is required`;
                if (values.display_type === 'BANNER' && value.length > 200) {
                    return t`Banner content must be 200 characters or less`;
                }
                return null;
            },
            emoji: (value, values) =>
                values.display_type === 'MODAL' && !value.trim() ? t`Emoji is required for modals` : null,
            target_account_ids: (value, values) =>
                values.target_type === 'ACCOUNTS' && value.length === 0 ? t`Select at least one account` : null,
            target_user_ids: (value, values) =>
                values.target_type === 'USERS' && value.length === 0 ? t`Select at least one user` : null,
            cta_url: (value, values) => {
                if (values.cta_label && !value) return t`A URL is required when a button label is set`;
                if (value && !/^https?:\/\//.test(value)) return t`URL must start with http:// or https://`;
                return null;
            },
            cta_label: (value, values) =>
                values.cta_url && !value ? t`A button label is required when a URL is set` : null,
        },
    });

    const toPayload = (values: AnnouncementFormValues): UpsertAnnouncementData => ({
        title: values.title,
        content: values.content,
        status: values.status,
        display_type: values.display_type,
        emoji: values.display_type === 'MODAL' ? values.emoji : null,
        target_type: values.target_type,
        target_account_ids: values.target_type === 'ACCOUNTS' ? values.target_account_ids : undefined,
        target_user_ids: values.target_type === 'USERS' ? values.target_user_ids : undefined,
        cta_label: values.cta_label || null,
        cta_url: values.cta_url || null,
    });

    const handleSubmit = (values: AnnouncementFormValues) => {
        const payload = toPayload(values);
        const onSuccess = () => {
            showSuccess(isEditing ? t`Announcement updated` : t`Announcement created`);
            onClose();
        };
        const onError = (error: any) => formErrorHandler(form, error);

        if (isEditing) {
            updateMutation.mutate({announcementId: announcement.id, data: payload}, {onSuccess, onError});
        } else {
            createMutation.mutate(payload, {onSuccess, onError});
        }
    };

    const previewAnnouncement = {
        id: 0,
        title: form.values.title || t`Announcement title`,
        content: form.values.content || t`Announcement content`,
        display_type: form.values.display_type,
        emoji: form.values.emoji,
        cta_label: form.values.cta_label || null,
        cta_url: form.values.cta_url || null,
    };

    const isBanner = form.values.display_type === 'BANNER';

    return (
        <Modal
            heading={isEditing ? t`Edit Announcement` : t`Create Announcement`}
            onClose={onClose}
            opened
        >
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <Stack gap="md">
                    <SegmentedControl
                        fullWidth
                        data={[
                            {label: t`Banner`, value: 'BANNER'},
                            {label: t`Modal`, value: 'MODAL'},
                        ]}
                        {...form.getInputProps('display_type')}
                    />

                    <TextInput
                        label={t`Title`}
                        placeholder={t`e.g. New feature: Waitlists`}
                        required
                        {...form.getInputProps('title')}
                    />

                    {isBanner ? (
                        <TextInput
                            label={t`Content`}
                            description={t`Keep it short — banners show a single line. ${form.values.content.length}/200`}
                            placeholder={t`e.g. Scheduled maintenance this Saturday from 2-4am UTC`}
                            maxLength={200}
                            required
                            {...form.getInputProps('content')}
                        />
                    ) : (
                        <Editor
                            label={t`Content`}
                            value={form.values.content}
                            onChange={(value) => form.setFieldValue('content', value)}
                            error={form.errors.content as string}
                            editorType="simple"
                        />
                    )}

                    {!isBanner && (
                        <Group align="flex-end" gap="md">
                            <TextInput
                                label={t`Emoji`}
                                description={t`Shown bouncing at the top of the modal`}
                                w={140}
                                {...form.getInputProps('emoji')}
                            />
                            <div className={classes.emojiPreview}>
                                <BouncingEmoji emoji={form.values.emoji || '🎉'} size={36}/>
                            </div>
                        </Group>
                    )}

                    <div>
                        <Text size="sm" fw={500} mb={4}>{t`Audience`}</Text>
                        <SegmentedControl
                            fullWidth
                            data={[
                                {label: t`All users`, value: 'ALL'},
                                {label: t`Specific accounts`, value: 'ACCOUNTS'},
                                {label: t`Specific users`, value: 'USERS'},
                            ]}
                            {...form.getInputProps('target_type')}
                        />
                    </div>

                    {form.values.target_type === 'ACCOUNTS' && (
                        <AnnouncementTargetPicker
                            targetType="ACCOUNTS"
                            value={form.values.target_account_ids}
                            onChange={(ids) => form.setFieldValue('target_account_ids', ids)}
                            initialLabels={announcement?.target_type === 'ACCOUNTS' ? announcement.target_names : {}}
                            error={form.errors.target_account_ids as string}
                        />
                    )}

                    {form.values.target_type === 'USERS' && (
                        <AnnouncementTargetPicker
                            targetType="USERS"
                            value={form.values.target_user_ids}
                            onChange={(ids) => form.setFieldValue('target_user_ids', ids)}
                            initialLabels={announcement?.target_type === 'USERS' ? announcement.target_names : {}}
                            error={form.errors.target_user_ids as string}
                        />
                    )}

                    <Group grow>
                        <TextInput
                            label={t`Button label`}
                            description={t`Optional call to action`}
                            placeholder={t`e.g. Learn more`}
                            {...form.getInputProps('cta_label')}
                        />
                        <TextInput
                            label={t`Button URL`}
                            description={t`Where the button links to`}
                            placeholder="https://"
                            {...form.getInputProps('cta_url')}
                        />
                    </Group>

                    <Select
                        label={t`Status`}
                        description={t`Only published announcements are shown to users`}
                        data={[
                            {label: t`Draft`, value: 'DRAFT'},
                            {label: t`Published`, value: 'PUBLISHED'},
                        ]}
                        allowDeselect={false}
                        {...form.getInputProps('status')}
                    />

                    {previewing && isBanner && (
                        <div className={classes.bannerPreview}>
                            <AnnouncementBanner
                                announcement={previewAnnouncement}
                                onDismiss={() => setPreviewing(false)}
                            />
                        </div>
                    )}

                    <Group grow>
                        <Button
                            variant="light"
                            leftSection={<IconEye size={16}/>}
                            onClick={() => setPreviewing((current) => !current)}
                            data-testid="announcement-preview-button"
                        >
                            {previewing && isBanner ? t`Hide Preview` : t`Preview`}
                        </Button>
                        <Button
                            loading={createMutation.isPending || updateMutation.isPending}
                            type="submit"
                            data-testid="announcement-submit-button"
                        >
                            {isEditing ? t`Save Changes` : t`Create Announcement`}
                        </Button>
                    </Group>
                </Stack>
            </form>

            {previewing && !isBanner && (
                <AnnouncementModal
                    announcement={previewAnnouncement}
                    onDismiss={() => setPreviewing(false)}
                    onClose={() => setPreviewing(false)}
                />
            )}
        </Modal>
    );
};

export default Announcements;
