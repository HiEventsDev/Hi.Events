import classes from './HomepageDesigner.module.scss';
import {useParams} from "react-router-dom";
import {useGetEventSettings} from "../../../../queries/useGetEventSettings.ts";
import {useUpdateEventSettings} from "../../../../mutations/useUpdateEventSettings.ts";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.tsx";
import {useEffect} from "react";
import {EventSettings} from "../../../../types.ts";
import {showSuccess} from "../../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";
import {useForm} from "@mantine/form";
import {Button, ColorInput, Group, TextInput} from "@mantine/core";
import {CoverUpload} from "./CoverUpload";
import {IconColorPicker, IconHelp, IconPhoto} from "@tabler/icons-react";
import {Tooltip} from "../../../common/Tooltip";
import EventHomepage from "../../../layouts/EventHomepage";
import {useGetEventPublic} from '../../../../queries/useGetEventPublic.ts';
import {LoadingMask} from '../../../common/LoadingMask';
import {CustomSelect} from "../../../common/CustomSelect";
import {useGetEventImages} from "../../../../queries/useGetEventImages.ts";

const HomepageDesigner = () => {
    const {eventId} = useParams();
    const eventSettingsQuery = useGetEventSettings(eventId);
    const publicEventQuery = useGetEventPublic(eventId);
    const updateMutation = useUpdateEventSettings();
    const eventImagesQuery = useGetEventImages(eventId);
    const existingCover = eventImagesQuery.data?.find((image) => image.type === 'EVENT_COVER');

    const form = useForm({
        initialValues: {
            homepage_background_color: '#fff',
            homepage_primary_color: '#444',
            homepage_primary_text_color: '#000000',
            homepage_secondary_color: '#444',
            homepage_secondary_text_color: '#fff',
            homepage_body_background_color: '#fff',
            homepage_background_type: 'COLOR',
            continue_button_text: '',
        }
    });
    const formErrorHandle = useFormErrorResponseHandler();

    useEffect(() => {
        if (eventSettingsQuery?.isFetched && eventSettingsQuery?.data) {
            form.setValues({
                homepage_background_color: eventSettingsQuery.data.homepage_background_color || '',
                homepage_primary_color: eventSettingsQuery.data.homepage_primary_color || '',
                homepage_primary_text_color: eventSettingsQuery.data.homepage_primary_text_color || '',
                homepage_secondary_color: eventSettingsQuery.data.homepage_secondary_color || '',
                homepage_secondary_text_color: eventSettingsQuery.data.homepage_secondary_text_color || '',
                homepage_body_background_color: eventSettingsQuery.data.homepage_body_background_color || '',
                homepage_background_type: eventSettingsQuery.data.homepage_background_type || 'COLOR',
                continue_button_text: eventSettingsQuery.data.continue_button_text,
            });
        }
    }, [eventSettingsQuery.isFetched]);

    const handleSubmit = (values: Partial<EventSettings>) => {
        updateMutation.mutate({
            eventSettings: values,
            eventId: eventId,
        }, {
            onSuccess: () => {
                showSuccess(t`Successfully Updated Homepage Design`);
            },
            onError: (error) => {
                formErrorHandle(form, error);
            }
        });
    }

    return (
        <div className={classes.container}>
            <div className={classes.sidebar}>
                <div className={classes.sticky}>
                    <h2>
                        {t`Homepage Design`}
                    </h2>
                    <Group justify={'space-between'}>
                        <h3>
                            {t`Cover`}
                        </h3>
                        <Tooltip label={t`We recommend dimensions of 2160px by 1080px, and a maximum file size of 5MB`}>
                            <IconHelp size={20}/>
                        </Tooltip>
                    </Group>
                    <CoverUpload/>

                    <h3>
                        {t`Colors`}
                    </h3>
                    <form onSubmit={form.onSubmit(handleSubmit as any)}>
                        <fieldset disabled={eventSettingsQuery.isLoading || updateMutation.isPending}>

                            <CustomSelect
                                optionList={
                                    [
                                        {
                                            icon: <IconColorPicker/>,
                                            label: t`Color`,
                                            value: 'COLOR',
                                            description: t`Choose a color for your background`,
                                        },
                                        {
                                            icon: <IconPhoto/>,
                                            label: t`Use cover image`,
                                            value: 'MIRROR_COVER_IMAGE',
                                            description: t`Use a blurred version of the cover image as the background`,
                                            disabled: !existingCover,
                                        },
                                    ]
                                }
                                form={form}
                                label={t`Background Type`}
                                name={'homepage_background_type'}
                            />

                            {form.values.homepage_background_type === 'COLOR' && (
                                <ColorInput label={t`Page background color`}
                                            {...form.getInputProps('homepage_body_background_color')}
                                />
                            )}
                            <ColorInput label={t`Content background color`}
                                        {...form.getInputProps('homepage_background_color')}
                            />
                            <ColorInput label={t`Primary Colour`}
                                        {...form.getInputProps('homepage_primary_color')}
                            />
                            <ColorInput label={t`Primary Text Color`}
                                        {...form.getInputProps('homepage_primary_text_color')}
                            />
                            <ColorInput label={t`Secondary color`}
                                        {...form.getInputProps('homepage_secondary_color')}
                            />
                            <ColorInput label={t`Secondary text color`}
                                        {...form.getInputProps('homepage_secondary_text_color')}
                            />
                            <TextInput
                                label={t`Continue button text`}
                                {...form.getInputProps('continue_button_text')}
                            />
                            <Button loading={updateMutation.isPending} type={'submit'}>
                                {t`Save Changes`}
                            </Button>
                        </fieldset>
                    </form>
                </div>
            </div>
            <div className={classes.previewContainer}>
                <h2>{t`Homepage Preview`}</h2>
                <div className={classes.preview}>
                    {!publicEventQuery.isFetched
                        ? <LoadingMask/>
                        : <EventHomepage
                            event={publicEventQuery.data}
                            continueButtonText={form.values.continue_button_text}
                            backgroundType={form.values.homepage_background_type as any}
                            colors={{
                                primary: form.values.homepage_primary_color,
                                primaryText: form.values.homepage_primary_text_color,
                                secondary: form.values.homepage_secondary_color,
                                secondaryText: form.values.homepage_secondary_text_color,
                                background: form.values.homepage_background_color,
                                bodyBackground: form.values.homepage_body_background_color,
                            }}
                        />}
                </div>

            </div>
        </div>
    )
};

export default HomepageDesigner;
