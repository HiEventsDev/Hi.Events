import {useState} from "react";
import {t, Trans} from "@lingui/macro";
import {ActionIcon, Alert, Button, Group, List, Loader, Modal, Stack, Text, TextInput} from "@mantine/core";
import {IconAlertTriangle, IconSearch, IconTrash} from "@tabler/icons-react";
import {useDebouncedValue} from "@mantine/hooks";
import {useGetAccountImages} from "../../../../../../queries/useGetAccountImages.ts";
import {useDeleteAccountImage} from "../../../../../../mutations/useDeleteAccountImage.ts";
import {Pagination} from "../../../../Pagination";
import {Image as ImageType} from "../../../../../../types.ts";
import {confirmationDialog} from "../../../../../../utilites/confirmationDialog.tsx";
import {showError, showSuccess} from "../../../../../../utilites/notifications.tsx";
import classes from './index.module.scss';

interface BrowseImagesPanelProps {
    onImageSelected: (url: string) => void;
    selectedUrl: string | null;
}

interface ImageReference {
    entity_type: string;
    entity_id: number;
    entity_name: string;
    field_label: string;
    is_protected: boolean;
    event_status?: string | null;
    event_lifecycle_status?: string | null;
}

interface InUseConflict {
    image: ImageType;
    message: string;
    references: ImageReference[];
    deletableWithConfirm: boolean;
}

export const BrowseImagesPanel = ({onImageSelected, selectedUrl}: BrowseImagesPanelProps) => {
    const [page, setPage] = useState(1);
    const [searchQuery, setSearchQuery] = useState('');
    const [debouncedQuery] = useDebouncedValue(searchQuery, 300);
    const [conflict, setConflict] = useState<InUseConflict | null>(null);

    const {data, isLoading} = useGetAccountImages({
        pageNumber: page,
        perPage: 24,
        query: debouncedQuery || undefined,
        sortBy: 'created_at',
        sortDirection: 'desc',
    });

    const deleteImage = useDeleteAccountImage();

    const handleSearchChange = (value: string) => {
        setSearchQuery(value);
        setPage(1);
    };

    const performDelete = (image: ImageType, confirm: boolean) => {
        deleteImage.mutate({imageId: image.id, confirm}, {
            onSuccess: () => {
                showSuccess(t`Image deleted`);
                setConflict(null);
                if (selectedUrl === image.url) {
                    onImageSelected('');
                }
            },
            onError: (error: any) => {
                const status = error?.response?.status;
                const body = error?.response?.data;

                if (status === 409 && Array.isArray(body?.references)) {
                    setConflict({
                        image,
                        message: body.message ?? t`Image is referenced by other content.`,
                        references: body.references,
                        deletableWithConfirm: !!body.deletable_with_confirm,
                    });
                    return;
                }

                showError(t`Could not delete image. Please try again.`);
            },
        });
    };

    const handleDelete = (image: ImageType) => {
        confirmationDialog(
            t`Delete "${image.file_name}" from the server? This cannot be undone.`,
            () => performDelete(image, false),
            {confirm: t`Delete`},
        );
    };

    const closeConflict = () => setConflict(null);

    const groupedReferences = (refs: ImageReference[]) => {
        const map = new Map<string, ImageReference[]>();
        for (const ref of refs) {
            const key = `${ref.entity_type}:${ref.entity_id}:${ref.entity_name}`;
            if (!map.has(key)) map.set(key, []);
            map.get(key)!.push(ref);
        }
        return Array.from(map.entries());
    };

    return (
        <Stack>
            <TextInput
                placeholder={t`Search by filename...`}
                leftSection={<IconSearch size={16}/>}
                value={searchQuery}
                onChange={(e) => handleSearchChange(e.currentTarget.value)}
            />

            {isLoading ? (
                <Stack align="center" py="xl">
                    <Loader size="lg"/>
                </Stack>
            ) : data?.data && data.data.length > 0 ? (
                <>
                    <div className={classes.imageGrid}>
                        {data.data.map((image: ImageType) => (
                            <div key={image.id}>
                                <div
                                    className={`${classes.thumbnail} ${selectedUrl === image.url ? classes.thumbnailSelected : ''}`}
                                    onClick={() => onImageSelected(image.url)}
                                >
                                    <img
                                        src={image.url}
                                        alt={image.file_name}
                                        loading="lazy"
                                        style={image.lqip_base64 ? {
                                            backgroundImage: `url(${image.lqip_base64})`,
                                            backgroundSize: 'cover',
                                        } : undefined}
                                    />
                                    <ActionIcon
                                        className={classes.deleteButton}
                                        variant="filled"
                                        color="red"
                                        size="sm"
                                        radius="xl"
                                        aria-label={t`Delete image`}
                                        onClick={(event) => {
                                            event.stopPropagation();
                                            handleDelete(image);
                                        }}
                                    >
                                        <IconTrash size={14}/>
                                    </ActionIcon>
                                </div>
                                <div className={classes.fileName} title={image.file_name}>
                                    {image.file_name}
                                </div>
                            </div>
                        ))}
                    </div>
                    <Pagination
                        total={data.meta.last_page}
                        value={page}
                        onChange={setPage}
                        marginTop={0}
                    />
                </>
            ) : (
                <Text c="dimmed" ta="center" py="xl" size="sm">
                    {debouncedQuery
                        ? t`No images matching "${debouncedQuery}"`
                        : t`No images yet. Upload an image using the Upload tab.`}
                </Text>
            )}

            <Modal
                opened={conflict !== null}
                onClose={closeConflict}
                title={t`Image is in use`}
                size="lg"
            >
                {conflict && (
                    <Stack>
                        <Alert color={conflict.deletableWithConfirm ? 'yellow' : 'red'} icon={<IconAlertTriangle/>}>
                            {conflict.message}
                        </Alert>

                        <Text size="sm" fw={600}>
                            <Trans>Referenced in:</Trans>
                        </Text>
                        <List size="sm" spacing="xs">
                            {groupedReferences(conflict.references).map(([key, refs]) => {
                                const first = refs[0];
                                const fields = refs.map(r => r.field_label).join(', ');
                                const lifecycle = first.event_lifecycle_status
                                    ? ` — ${first.event_lifecycle_status}${first.event_status ? `/${first.event_status}` : ''}`
                                    : '';
                                return (
                                    <List.Item key={key}>
                                        <Text size="sm">
                                            <strong>{first.entity_name}</strong> ({fields}){lifecycle}
                                            {first.is_protected && (
                                                <Text component="span" c="red" size="xs" ml={6}>
                                                    <Trans>active — blocks delete</Trans>
                                                </Text>
                                            )}
                                        </Text>
                                    </List.Item>
                                );
                            })}
                        </List>

                        <Group justify="flex-end">
                            <Button variant="default" onClick={closeConflict}>
                                <Trans>Cancel</Trans>
                            </Button>
                            {conflict.deletableWithConfirm && (
                                <Button
                                    color="red"
                                    loading={deleteImage.isPending}
                                    onClick={() => performDelete(conflict.image, true)}
                                >
                                    <Trans>Delete anyway</Trans>
                                </Button>
                            )}
                        </Group>
                    </Stack>
                )}
            </Modal>
        </Stack>
    );
};
