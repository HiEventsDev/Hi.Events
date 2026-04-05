import {t} from "@lingui/macro";
import {Button, Switch, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useParams} from "react-router";
import {useEffect} from "react";
import {EventSettings} from "../../../../../../types.ts";
import {Card} from "../../../../../common/Card";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useUpdateEventSettings} from "../../../../../../mutations/useUpdateEventSettings.ts";
import {useGetEventSettings} from "../../../../../../queries/useGetEventSettings.ts";
import {HeadingWithDescription} from "../../../../../common/Card/CardHeading";
import {InputGroup} from "../../../../../common/InputGroup";
import {
    IconBrandFacebook,
    IconBrandInstagram,
    IconBrandLinkedin,
    IconBrandTiktok,
    IconBrandX,
    IconBrandYoutube,
} from "@tabler/icons-react";

export const SocialLinksSettings = () => {
    const {eventId} = useParams();
    const eventSettingsQuery = useGetEventSettings(eventId);
    const updateMutation = useUpdateEventSettings();
    const form = useForm({
        initialValues: {
            show_social_media_handles: false,
            social_media_handles: {
                facebook: '',
                instagram: '',
                twitter: '',
                linkedin: '',
                youtube: '',
                tiktok: '',
            } as Record<string, string>,
        }
    });
    const formErrorHandle = useFormErrorResponseHandler();

    useEffect(() => {
        if (eventSettingsQuery?.isFetched && eventSettingsQuery?.data) {
            const handles = eventSettingsQuery.data.social_media_handles || {};
            form.setValues({
                show_social_media_handles: eventSettingsQuery.data.show_social_media_handles ?? false,
                social_media_handles: {
                    facebook: handles.facebook || '',
                    instagram: handles.instagram || '',
                    twitter: handles.twitter || '',
                    linkedin: handles.linkedin || '',
                    youtube: handles.youtube || '',
                    tiktok: handles.tiktok || '',
                },
            });
        }
    }, [eventSettingsQuery.isFetched]);

    const handleSubmit = (values: Partial<EventSettings>) => {
        updateMutation.mutate({
            eventSettings: values,
            eventId: eventId,
        }, {
            onSuccess: () => {
                showSuccess(t`Successfully Updated Social Links`);
            },
            onError: (error) => {
                formErrorHandle(form, error);
            }
        });
    }

    return (
        <Card>
            <HeadingWithDescription
                heading={t`Social Links`}
                description={t`Add social media links to display on your event page`}
            />
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <fieldset disabled={eventSettingsQuery.isLoading || updateMutation.isPending}>
                    <Switch
                        {...form.getInputProps('show_social_media_handles', {type: 'checkbox'})}
                        description={t`Show social media links on the public event page`}
                        label={t`Show social links`}
                    />
                    <InputGroup>
                        <TextInput
                            {...form.getInputProps('social_media_handles.facebook')}
                            label={t`Facebook`}
                            placeholder="username"
                            leftSection={<IconBrandFacebook size={18}/>}
                        />
                        <TextInput
                            {...form.getInputProps('social_media_handles.instagram')}
                            label={t`Instagram`}
                            placeholder="username"
                            leftSection={<IconBrandInstagram size={18}/>}
                        />
                    </InputGroup>
                    <InputGroup>
                        <TextInput
                            {...form.getInputProps('social_media_handles.twitter')}
                            label={t`X (Twitter)`}
                            placeholder="username"
                            leftSection={<IconBrandX size={18}/>}
                        />
                        <TextInput
                            {...form.getInputProps('social_media_handles.linkedin')}
                            label={t`LinkedIn`}
                            placeholder="username"
                            leftSection={<IconBrandLinkedin size={18}/>}
                        />
                    </InputGroup>
                    <InputGroup>
                        <TextInput
                            {...form.getInputProps('social_media_handles.youtube')}
                            label={t`YouTube`}
                            placeholder="channel"
                            leftSection={<IconBrandYoutube size={18}/>}
                        />
                        <TextInput
                            {...form.getInputProps('social_media_handles.tiktok')}
                            label={t`TikTok`}
                            placeholder="username"
                            leftSection={<IconBrandTiktok size={18}/>}
                        />
                    </InputGroup>
                    <Button loading={updateMutation.isPending} type={'submit'}>
                        {t`Save`}
                    </Button>
                </fieldset>
            </form>
        </Card>
    );
}
