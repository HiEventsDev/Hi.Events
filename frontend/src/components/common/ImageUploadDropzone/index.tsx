import { useEffect, useState, useRef } from "react";
import { Dropzone, IMAGE_MIME_TYPE } from "@mantine/dropzone";
import { useUploadImage } from "../../../mutations/useUploadImage.ts";
import { useDeleteImage } from "../../../mutations/useDeleteImage.ts";
import { showSuccess } from "../../../utilites/notifications.tsx";
import { Button, Group, Loader, Paper, Text } from "@mantine/core";
import { IconReplace, IconTrash, IconUpload } from "@tabler/icons-react";
import { t } from "@lingui/macro";
import { IdParam, ImageType } from "../../../types.ts";
import classes from "./ImageUploadDropzone.module.scss";

const MAX_UPLOAD_SIZE = 5 * 1024 * 1024; // 5MB

interface ImageUploadDropzoneProps {
    onDrop?: (acceptedFiles: File[]) => void;
    onDropRejected?: (rejectedFiles: File[]) => void;
    disabled?: boolean;
    helpText?: string;
    imageType?: ImageType;
    entityId?: IdParam;
    existingImageData?: {
        url?: string;
        id?: IdParam;
        dimensions?: { width: number; height: number };
    };
}

export const ImageUploadDropzone = ({
                                        onDrop,
                                        onDropRejected,
                                        disabled,
                                        helpText,
                                        imageType,
                                        entityId,
                                        existingImageData,
                                    }: ImageUploadDropzoneProps) => {
    const [loading, setLoading] = useState(false);
    const [previewImage, setPreviewImage] = useState(existingImageData?.url || null);
    const fileInputRef = useRef<HTMLInputElement | null>(null);

    const uploadImage = useUploadImage();
    const deleteImage = useDeleteImage();

    useEffect(() => {
        if (existingImageData?.url) {
            setPreviewImage(existingImageData.url);
        }
    }, [existingImageData]);

    const handleDrop = (files: File[]) => {
        const [file] = files;
        setLoading(true);
        onDrop?.(files);

        uploadImage.mutate(
            { image: file, imageType, entityId },
            {
                onSuccess: (response) => {
                    const uploadedUrl = response?.data?.url;
                    if (uploadedUrl) {
                        setPreviewImage(uploadedUrl);
                        showSuccess(t`Image uploaded successfully`);
                    }
                    setLoading(false);
                },
                onError: (error) => {
                    console.error(error);
                    setLoading(false);
                },
            }
        );
    };

    const handleDelete = () => {
        if (!previewImage) return;
        setLoading(true);

        deleteImage.mutate(
            { imageId: existingImageData?.id },
            {
                onSuccess: () => {
                    setPreviewImage(null);
                    showSuccess(t`Image deleted successfully`);
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
                    <Loader size="md" />
                    <Text size="sm" mt="xs" c="dimmed">
                        Processing image...
                    </Text>
                </div>
            );
        }

        if (previewImage) {
            return (
                <div className={classes.previewContainer}>
                    <img src={previewImage} alt="Uploaded preview" className={classes.previewImage} />
                    <Button
                        variant="light"
                        color="blue"
                        size="xs"
                        leftSection={<IconReplace size={14} />}
                        onClick={handleReplace}
                        className={classes.replaceButton}
                    >
                        Replace Image
                    </Button>
                </div>
            );
        }

        return (
            <div className={classes.emptyDropzone}>
                <Group justify="center">
                    <div className={classes.iconWrapper}>
                        <IconUpload size={36} stroke={1.5} />
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
        <div className={classes.outerContainer}>
            <div className={classes.container}>
                <Dropzone
                    onDrop={handleDrop}
                    onReject={onDropRejected}
                    accept={IMAGE_MIME_TYPE}
                    maxSize={MAX_UPLOAD_SIZE}
                    disabled={disabled || loading}
                    className={classes.dropzone}
                    classNames={{ root: classes.dropzoneRoot, inner: classes.dropzoneInner }}
                >
                    {renderDropzoneContent()}
                    <input
                        type="file"
                        accept={IMAGE_MIME_TYPE.join(",")}
                        style={{ display: "none" }}
                        ref={fileInputRef}
                        onChange={(e) => {
                            if (e.target.files?.length) handleDrop(Array.from(e.target.files));
                        }}
                    />
                </Dropzone>
            </div>

            {previewImage && (
                <Group justify="end" mt="xs">
                    <Button
                        variant="outline"
                        color="red"
                        size="xs"
                        leftSection={<IconTrash size={14} />}
                        onClick={handleDelete}
                    >
                        Delete Image
                    </Button>
                </Group>
            )}
        </div>
    );
};
