import {t} from "@lingui/macro";
import {useParams} from "react-router-dom";
import {useFormErrorResponseHandler} from "../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useGetEventImages} from "../../../../../queries/useGetEventImages.ts";
import {Dropzone, FileWithPath, IMAGE_MIME_TYPE} from '@mantine/dropzone';
import {Anchor, Image as Img, LoadingOverlay, Tooltip} from '@mantine/core';
import {useEffect, useState} from "react";
import {useUploadEventImage} from "../../../../../mutations/useUploadEventImage.ts";
import {useForm} from "@mantine/form";
import {showError, showSuccess} from "../../../../../utilites/notifications.tsx";
import {useDeleteEventImage} from "../../../../../mutations/useDeleteEventImage.ts";
import classes from "./CoverUpload.module.scss";
import {IconTrash} from "@tabler/icons-react";

export const CoverUpload = () => {
    const {eventId} = useParams();
    const eventImagesQuery = useGetEventImages(eventId);
    const formErrorHandle = useFormErrorResponseHandler();
    const uploadMutation = useUploadEventImage();
    const deleteImageMutation = useDeleteEventImage()
    const [files, setFiles] = useState<FileWithPath[]>([]);
    const [loading, setLoading] = useState(false);
    const form = useForm({
        initialValues: {
            image: files
        }
    });

    const existingCover = eventImagesQuery.data?.find((image) => image.type === 'EVENT_COVER');

    const preview = files.map((file, index) => {
        const imageUrl = URL.createObjectURL(file);
        return <Img style={{width: '100%'}} key={index} src={imageUrl} onLoad={() => URL.revokeObjectURL(imageUrl)}/>;
    });

    useEffect(() => {
        if (files.length === 0) {
            return;
        }

        setLoading(true);
        const image = new Image();
        image.src = URL.createObjectURL(files[0]);
        image.onload = () => {
            if (image.width > 3000 || image.height > 2000) {
                showError(t`Image dimensions must be between 3000px by 2000px. With a max height of 2000px and max width of 3000px`);
                setFiles([]);
            } else if (files[0].size > 5000000) {
                showError(t`Image must be less than 5MB`);
                setFiles([]);
            } else if (image.width < 900 || image.height < 50) {
                showError(t`Image width must be at least 900px and height at least 50px`);
                setFiles([]);
            } else {
                uploadMutation.mutate({
                    eventId: eventId,
                    image: files[0]
                }, {
                    onError: (error) => {
                        formErrorHandle(form, error);
                    },
                    onSuccess: () => {
                        showSuccess(t`Image uploaded successfully`);
                        setFiles([]);
                    },
                    onSettled: () => setLoading(false),
                });
            }
        }
    }, [files]);

    return (
        <form style={{position: 'relative'}}>
            <fieldset disabled={uploadMutation.isLoading}>
                {(uploadMutation.isLoading || loading || deleteImageMutation.isLoading) && (
                    <LoadingOverlay visible={uploadMutation.isLoading} opacity={0.6}/>
                )}
                <Dropzone accept={IMAGE_MIME_TYPE} onDrop={setFiles} className={classes.dropZone}>
                    <div>
                        {existingCover ? t`Change Cover` : t`Upload Cover`}
                    </div>
                    <span>
                        {t`Drag and drop or click`}
                    </span>
                </Dropzone>

                {preview.length > 0 && (
                    <div className={classes.imagePreview}>
                        {preview}
                    </div>
                )}

                {(existingCover && preview.length === 0) &&
                    (
                        <div className={classes.imagePreview}>
                            <Img src={existingCover.url} style={{width: '100%'}}/>
                            <Anchor
                                aria-label={t`Delete Image`}
                                variant={'transparent'}
                                className={classes.deleteButton}
                                onClick={(event) => {
                                    event.preventDefault();
                                    deleteImageMutation.mutate({
                                        imageId: existingCover.id,
                                        eventId: eventId,
                                    }, {
                                        onSuccess: () => {
                                            showSuccess(t`Image deleted successfully`);
                                        }
                                    })
                                }}
                            >
                                <Tooltip label={t`Delete Cover`}>
                                    <IconTrash size={20}/>
                                </Tooltip>
                            </Anchor>
                        </div>
                    )
                }
            </fieldset>
        </form>
    );
}