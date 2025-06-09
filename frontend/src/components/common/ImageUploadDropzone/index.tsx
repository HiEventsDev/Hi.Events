import {useEffect, useRef, useState} from "react";
import {Dropzone, IMAGE_MIME_TYPE} from "@mantine/dropzone";
import {useUploadImage} from "../../../mutations/useUploadImage.ts";
import {useDeleteImage} from "../../../mutations/useDeleteImage.ts";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {ActionIcon, Button, Group, Loader, Text} from "@mantine/core";
import {IconReplace, IconTrash, IconUpload} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {IdParam, ImageType} from "../../../types.ts";
import classes from "./ImageUploadDropzone.module.scss";

const MAX_UPLOAD_SIZE = 5 * 1024 * 1024; // 5MB

interface ImageUploadDropzoneProps {
    disabled?: boolean;
    helpText?: string;
    imageType: ImageType;
    entityId: IdParam;
    onUploadSuccess?: () => void;
    onDeleteSuccess?: () => void;
    existingImageData?: {
        url?: string;
        id?: IdParam;
    };
    displayMode?: 'normal' | 'compact';
}

export const ImageUploadDropzone = ({
                                        disabled,
                                        helpText,
                                        imageType,
                                        entityId,
                                        existingImageData,
                                        onUploadSuccess,
                                        onDeleteSuccess,
                                        displayMode = 'normal'
                                    }: ImageUploadDropzoneProps) => {
    const [loading, setLoading] = useState(false);
    const [previewImage, setPreviewImage] = useState(existingImageData?.url || null);
    const [imageId, setImageId] = useState(existingImageData?.id || null);
    const [errors, setErrors] = useState<string[]>([]);
    const fileInputRef = useRef<HTMLInputElement | null>(null);

    const uploadImage = useUploadImage();
    const deleteImage = useDeleteImage();

    useEffect(() => {
        if (existingImageData?.url) {
            setPreviewImage(existingImageData.url);
            setImageId(existingImageData.id || null);
        }
    }, [existingImageData]);

    const handleDrop = (files: File[]) => {
        const [file] = files;
        if (!file) return;

        setErrors([]);
        setLoading(true);

        uploadImage.mutate(
            {image: file, imageType, entityId},
            {
                onSuccess: (response) => {
                    const uploadedUrl = response?.data?.url;
                    const uploadedId = response?.data?.id;

                    if (uploadedUrl && uploadedId) {
                        setPreviewImage(uploadedUrl);
                        setImageId(uploadedId);
                        showSuccess(t`Image uploaded successfully`);
                        onUploadSuccess?.();
                    }
                    setLoading(false);
                    setErrors([]);
                },
                onError: (error: any) => {
                    console.error(error);
                    setLoading(false);

                    // Extract error messages from the response
                    let errorMessages: string[];
                    if (error?.response?.data?.errors?.image) {
                        errorMessages = error.response.data.errors.image;
                    } else if (error?.response?.data?.message) {
                        errorMessages = [error.response.data.message];
                    } else {
                        errorMessages = [t`Failed to upload image. Please try again.`];
                    }

                    setErrors(errorMessages);
                },
            }
        );
    };

    const handleDelete = () => {
        if (!previewImage || !imageId) return;

        setLoading(true);
        setErrors([]);

        deleteImage.mutate(
            {imageId},
            {
                onSuccess: () => {
                    setPreviewImage(null);
                    setImageId(null);
                    showSuccess(t`Image deleted successfully`);
                    setErrors([]);
                    onDeleteSuccess?.();
                },
                onError: (error) => {
                    console.error(error);
                },
                onSettled: () => setLoading(false),
            }
        );
    };

    const handleReplace = () => {
        fileInputRef.current?.click();
    };

    const renderDropzoneContent = () => {
        if (loading) {
            return (
                <div className={classes.loadingContainer}>
                    <Loader size={displayMode === 'compact' ? 'sm' : 'md'}/>
                    {displayMode !== 'compact' && (
                        <Text size="sm" mt="xs" c="dimmed">
                            Processing image...
                        </Text>
                    )}
                </div>
            );
        }

        if (previewImage) {
            return (
                <div className={classes.previewContainer}>
                    <img src={previewImage} alt="Uploaded preview" className={classes.previewImage}/>
                    <Button
                        variant="light"
                        color="blue"
                        size="xs"
                        leftSection={<IconReplace size={14}/>}
                        onClick={handleReplace}
                        className={classes.replaceButton}
                    >
                        Replace Image
                    </Button>
                </div>
            );
        }

        if (displayMode === 'compact') {
            return (
                <div className={classes.emptyDropzoneCompact}>
                    <Group justify="center" gap="xs">
                        <IconUpload size={20} stroke={1.5}/>
                        <Text size="sm" fw={500}>
                            Click to upload
                        </Text>
                    </Group>
                    {helpText && (
                        <Text ta="center" c="dimmed" size="xs" mt={4}>
                            {helpText}
                        </Text>
                    )}
                </div>
            );
        }

        return (
            <div className={classes.emptyDropzone}>
                <Group justify="center">
                    <div className={classes.iconWrapper}>
                        <IconUpload size={36} stroke={1.5}/>
                    </div>
                </Group>
                <Text ta="center" fw={600} size="md" mt="md">
                    Drag & drop or click to upload
                </Text>
                {helpText && (
                    <Text ta="center" c="dimmed" size="sm" mt="xs">
                        {helpText}
                    </Text>
                )}
                <Text ta="center" c="dimmed" size="xs" mt="xs">
                    Images only · Max 5MB
                </Text>
            </div>
        );
    };

    return (
        <div className={`${classes.outerContainer} ${displayMode === 'compact' ? classes.compact : ''}`}>
            <div className={classes.container}>
                <Dropzone
                    onDrop={handleDrop}
                    accept={IMAGE_MIME_TYPE}
                    maxSize={MAX_UPLOAD_SIZE}
                    disabled={disabled || loading}
                    className={classes.dropzone}
                    classNames={{
                        root: `${classes.dropzoneRoot} ${errors.length > 0 ? classes.dropzoneError : ''}`,
                        inner: classes.dropzoneInner
                    }}
                >
                    {renderDropzoneContent()}
                    <input
                        type="file"
                        accept={IMAGE_MIME_TYPE.join(",")}
                        style={{display: "none"}}
                        ref={fileInputRef}
                        onChange={(e) => {
                            if (e.target.files?.length) handleDrop(Array.from(e.target.files));
                        }}
                    />
                </Dropzone>
            </div>

            {errors.length > 0 && (
                <div className={classes.errorContainer}>
                    {errors.map((error, index) => (
                        <Text key={index} size="xs" c="red">
                            {error}
                        </Text>
                    ))}
                </div>
            )}

            {previewImage && imageId && (
                <Group justify="end" mt="xs">
                    <ActionIcon
                        variant="outline"
                        color="red"
                        title={t`Delete image`}
                        onClick={handleDelete}
                        disabled={loading}
                    >
                        <IconTrash size={14}/>
                    </ActionIcon>
                </Group>
            )}
        </div>
    );
};
