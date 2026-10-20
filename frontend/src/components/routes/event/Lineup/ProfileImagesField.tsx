import {useState} from "react";
import {t} from "@lingui/macro";
import {ActionIcon, Loader} from "@mantine/core";
import {Dropzone, IMAGE_MIME_TYPE} from "@mantine/dropzone";
import {IconGripVertical, IconPhotoPlus, IconX} from "@tabler/icons-react";
import {useQueryClient} from "@tanstack/react-query";
import {
    closestCenter,
    DndContext,
    DragEndEvent,
    KeyboardSensor,
    PointerSensor,
    TouchSensor,
    useSensor,
    useSensors,
} from "@dnd-kit/core";
import {arrayMove, rectSortingStrategy, SortableContext, sortableKeyboardCoordinates, useSortable} from "@dnd-kit/sortable";
import {CSS} from "@dnd-kit/utilities";
import {Profile} from "../../../../api/lineup.client.ts";
import {imageClient} from "../../../../api/image.client.ts";
import {GET_EVENT_LINEUP_QUERY_KEY} from "../../../../queries/useGetEventLineup.ts";
import {GET_EVENT_LINEUP_PUBLIC_QUERY_KEY} from "../../../../queries/useGetEventLineupPublic.ts";
import {GET_PROFILES_QUERY_KEY} from "../../../../queries/useGetProfiles.ts";
import {useSortProfileImages} from "../../../../mutations/useSortProfileImages.ts";
import {useImageUploads} from "../../../../hooks/useImageUploads.ts";
import {useSortableAccessibility} from "../../../../hooks/useSortableAccessibility.ts";
import {showError} from "../../../../utilites/notifications.tsx";
import {confirmationDialog} from "../../../../utilites/confirmationDialog.tsx";
import {IdParam, Image} from "../../../../types.ts";
import classes from "./Lineup.module.scss";

const MAX_IMAGES = 8;
const MAX_FILE_SIZE = 5 * 1024 * 1024;

interface ProfileImagesFieldProps {
    profile: Profile;
    organizerId: IdParam;
    disabled?: boolean;
}

export const ProfileImagesField = ({profile, organizerId, disabled}: ProfileImagesFieldProps) => {
    const queryClient = useQueryClient();
    const sortMutation = useSortProfileImages();
    const [removingId, setRemovingId] = useState<IdParam | null>(null);
    const [pendingOrder, setPendingOrder] = useState<number[] | null>(null);
    const sensors = useSensors(
        useSensor(PointerSensor, {activationConstraint: {distance: 4}}),
        useSensor(TouchSensor, {activationConstraint: {delay: 150, tolerance: 6}}),
        useSensor(KeyboardSensor, {coordinateGetter: sortableKeyboardCoordinates}),
    );

    const images = pendingOrder
        ? pendingOrder
            .map(id => profile.images.find(image => Number(image.id) === id))
            .filter((image): image is Image => !!image)
        : profile.images;
    const imageIds = images.map(image => Number(image.id));
    const {id: dndId, accessibility} = useSortableAccessibility(imageIds, id => {
        const position = imageIds.indexOf(Number(id)) + 1;
        return t`Photo ${position}`;
    });

    const refresh = () => Promise.all([
        queryClient.invalidateQueries({queryKey: [GET_EVENT_LINEUP_QUERY_KEY]}),
        queryClient.invalidateQueries({queryKey: [GET_EVENT_LINEUP_PUBLIC_QUERY_KEY]}),
        queryClient.invalidateQueries({queryKey: [GET_PROFILES_QUERY_KEY]}),
    ]);

    const {uploading, upload} = useImageUploads({
        entityId: profile.id,
        type: 'PROFILE_IMAGE',
        room: MAX_IMAGES - profile.images.length,
        onLimitReached: () => showError(t`A profile can have up to ${MAX_IMAGES} photos.`),
        onUploaded: refresh,
    });

    const remove = async (imageId: IdParam) => {
        setRemovingId(imageId);
        try {
            await imageClient.delete(imageId);
            await refresh();
        } catch {
            showError(t`Could not remove the photo`);
        } finally {
            setRemovingId(null);
        }
    };

    const confirmRemove = (imageId: IdParam) => confirmationDialog(
        t`Remove this photo? It's deleted straight away.`,
        () => remove(imageId),
        {confirm: t`Remove`, destructive: true},
    );

    const handleDragEnd = ({active, over}: DragEndEvent) => {
        if (!over || active.id === over.id) {
            return;
        }

        const sortedIds = arrayMove(imageIds, imageIds.indexOf(Number(active.id)), imageIds.indexOf(Number(over.id)));
        setPendingOrder(sortedIds);

        sortMutation.mutate({organizerId, profileId: profile.id, sortedIds}, {
            onError: () => showError(t`Could not save the photo order. Please try again.`),
            onSettled: () => setPendingOrder(null),
        });
    };

    return (
        <div className={classes.fieldStack}>
            <DndContext id={dndId} accessibility={accessibility} sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
                <SortableContext items={imageIds} strategy={rectSortingStrategy} disabled={disabled}>
                    <div className={classes.photoGrid} data-testid="lineup-profile-photos">
                        {images.map((image, index) => (
                            <SortablePhoto
                                key={image.id}
                                image={image}
                                position={index + 1}
                                total={images.length}
                                disabled={disabled}
                                removing={removingId === image.id}
                                onRemove={() => confirmRemove(image.id)}
                            />
                        ))}

                        {Array.from({length: uploading}).map((_, index) => (
                            <div key={`uploading-${index}`} className={classes.photoTile} data-pending>
                                <Loader size="sm"/>
                            </div>
                        ))}

                        {!disabled && profile.images.length + uploading < MAX_IMAGES && (
                            <Dropzone
                                className={classes.photoDropzone}
                                onDrop={upload}
                                onReject={() => showError(t`Use a JPG, PNG or WebP image under 5MB.`)}
                                accept={IMAGE_MIME_TYPE}
                                maxSize={MAX_FILE_SIZE}
                                multiple
                                data-testid="lineup-profile-photo-dropzone"
                            >
                                <IconPhotoPlus size={22} stroke={1.5}/>
                                <span>{t`Add photos`}</span>
                            </Dropzone>
                        )}
                    </div>
                </SortableContext>
            </DndContext>
            <p className={classes.fieldHelp}>
                {profile.images.length > 1
                    ? t`Drag to reorder. The first photo is used on the lineup.`
                    : t`The first photo is used on the lineup. Square or portrait photos at least 600px wide look best.`}
            </p>
        </div>
    );
};

interface SortablePhotoProps {
    image: Image;
    position: number;
    total: number;
    disabled?: boolean;
    removing: boolean;
    onRemove: () => void;
}

const SortablePhoto = ({image, position, total, disabled, removing, onRemove}: SortablePhotoProps) => {
    const sortable = !disabled && total > 1;
    const {attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging} = useSortable({id: Number(image.id), disabled: !sortable});

    return (
        <div
            ref={setNodeRef}
            className={classes.photoTile}
            data-sortable={sortable || undefined}
            data-dragging={isDragging || undefined}
            data-testid={`lineup-profile-photo-${image.id}`}
            style={{
                backgroundColor: image.avg_colour ?? undefined,
                transform: CSS.Transform.toString(transform),
                transition,
            }}
            {...listeners}
        >
            <img src={image.url} alt="" draggable={false}/>
            {position === 1 && <span className={classes.photoBadge}>{t`Main photo`}</span>}
            {sortable && (
                <ActionIcon
                    ref={setActivatorNodeRef}
                    className={classes.photoMove}
                    size="md"
                    radius="xl"
                    variant="filled"
                    color="dark"
                    {...attributes}
                    aria-label={t`Move photo ${position} of ${total}`}
                >
                    <IconGripVertical size={14}/>
                </ActionIcon>
            )}
            {!disabled && (
                <ActionIcon
                    className={classes.photoRemove}
                    size="md"
                    radius="xl"
                    variant="filled"
                    color="dark"
                    loading={removing}
                    aria-label={t`Remove photo ${position}`}
                    onPointerDown={event => event.stopPropagation()}
                    onClick={onRemove}
                >
                    <IconX size={14}/>
                </ActionIcon>
            )}
        </div>
    );
};
