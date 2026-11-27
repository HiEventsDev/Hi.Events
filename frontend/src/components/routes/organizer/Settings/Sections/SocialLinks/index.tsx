import {useParams} from "react-router";
import {useForm} from "@mantine/form";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useEffect, useMemo, useState} from "react";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";
import {SettingsBlock, SettingsPage, SettingsRow, SettingsSaveFooter, SettingsSection} from "../../../../../common/SettingsPage";
import {Button, TextInput} from "@mantine/core";
import {useGetOrganizerSettings} from "../../../../../../queries/useGetOrganizerSettings.ts";
import {useUpdateOrganizerSettings} from "../../../../../../mutations/useUpdateOrganizerSettings.ts";
import {
    IconBrandBluesky,
    IconBrandDiscord,
    IconBrandFacebook,
    IconBrandGithub,
    IconBrandInstagram,
    IconBrandLinkedin,
    IconBrandPinterest,
    IconBrandReddit,
    IconBrandSnapchat,
    IconBrandTelegram,
    IconBrandTiktok,
    IconBrandTwitch,
    IconBrandVimeo,
    IconBrandVk,
    IconBrandWeibo,
    IconBrandWhatsapp,
    IconBrandX,
    IconBrandYoutube,
    IconChevronDown,
    IconChevronUp,
} from '@tabler/icons-react';

interface SocialPlatform {
    name: string;
    field: string;
    icon: any;
    placeholder: string;
    priority?: 'primary' | 'secondary';
}

const getSocialPlatforms = (): SocialPlatform[] => [
    {
        name: t`Facebook`,
        field: 'facebook_handle',
        icon: IconBrandFacebook,
        placeholder: 'username',
        priority: 'primary'
    },
    {
        name: t`Instagram`,
        field: 'instagram_handle',
        icon: IconBrandInstagram,
        placeholder: 'username',
        priority: 'primary'
    },
    {name: t`X (Twitter)`, field: 'twitter_handle', icon: IconBrandX, placeholder: 'username', priority: 'primary'},
    {
        name: t`LinkedIn`,
        field: 'linkedin_handle',
        icon: IconBrandLinkedin,
        placeholder: 'username',
        priority: 'primary'
    },
    {name: t`YouTube`, field: 'youtube_handle', icon: IconBrandYoutube, placeholder: 'channel', priority: 'primary'},

    {name: t`TikTok`, field: 'tiktok_handle', icon: IconBrandTiktok, placeholder: 'username', priority: 'primary'},
    {name: t`Bluesky`, field: 'bluesky_handle', icon: IconBrandBluesky, placeholder: 'name.bsky.social', priority: 'primary'},
    {name: t`Discord`, field: 'discord_handle', icon: IconBrandDiscord, placeholder: 'user_id', priority: 'secondary'},
    {
        name: t`Snapchat`,
        field: 'snapchat_handle',
        icon: IconBrandSnapchat,
        placeholder: 'username',
        priority: 'secondary'
    },
    {name: t`Twitch`, field: 'twitch_handle', icon: IconBrandTwitch, placeholder: 'username', priority: 'secondary'},
    {name: t`Reddit`, field: 'reddit_handle', icon: IconBrandReddit, placeholder: 'username', priority: 'secondary'},
    {
        name: t`Pinterest`,
        field: 'pinterest_handle',
        icon: IconBrandPinterest,
        placeholder: 'username',
        priority: 'secondary'
    },
    {
        name: t`WhatsApp`,
        field: 'whatsapp_handle',
        icon: IconBrandWhatsapp,
        placeholder: 'phone_number',
        priority: 'secondary'
    },
    {
        name: t`Telegram`,
        field: 'telegram_handle',
        icon: IconBrandTelegram,
        placeholder: 'username',
        priority: 'secondary'
    },
    {name: t`GitHub`, field: 'github_handle', icon: IconBrandGithub, placeholder: 'username', priority: 'secondary'},
    {name: t`Vimeo`, field: 'vimeo_handle', icon: IconBrandVimeo, placeholder: 'username', priority: 'secondary'},
    {name: t`VK`, field: 'vk_handle', icon: IconBrandVk, placeholder: 'username', priority: 'secondary'},
    {name: t`Weibo`, field: 'weibo_handle', icon: IconBrandWeibo, placeholder: 'username', priority: 'secondary'},
];

export const SocialLinks = () => {
    const {organizerId} = useParams();
    const organizerSettingsQuery = useGetOrganizerSettings(organizerId);
    const updateMutation = useUpdateOrganizerSettings();
    const [showMore, setShowMore] = useState(false);
    const socialPlatforms = getSocialPlatforms();

    const initialValues = socialPlatforms.reduce((acc, platform) => {
        acc[platform.field] = '';
        return acc;
    }, {} as Record<string, string>);

    const form = useForm({
        initialValues
    });

    const formErrorHandle = useFormErrorResponseHandler();

    useEffect(() => {
        if (organizerSettingsQuery?.isFetched && organizerSettingsQuery?.data) {
            const formValues: Record<string, string> = {};

            if (organizerSettingsQuery.data.website_url) {
                formValues.website_url = organizerSettingsQuery.data.website_url;
            }

            const handles: Record<string, string | undefined> | undefined = organizerSettingsQuery.data.social_media_handles;
            if (handles) {
                socialPlatforms.forEach(platform => {
                    if (platform.field !== 'website_url') {
                        const handle = handles[platform.field.replace('_handle', '')];
                        if (handle) {
                            formValues[platform.field] = handle;
                        }
                    }
                });
            }

            const values = {...initialValues, ...formValues};
            form.setInitialValues(values);
            form.setValues(values);
        }
    }, [organizerSettingsQuery.isFetched]);

    const hasSecondaryValues = useMemo(() => {
        return socialPlatforms
            .filter(p => p.priority === 'secondary')
            .some(platform => form.values[platform.field] && form.values[platform.field].trim() !== '');
    }, [form.values]);

    useEffect(() => {
        if (hasSecondaryValues) {
            setShowMore(true);
        }
    }, [hasSecondaryValues]);

    const handleSubmit = (values: Record<string, string>) => {
        updateMutation.mutate({
            organizerSettings: values,
            organizerId: organizerId,
        }, {
            onSuccess: () => {
                form.resetDirty();
                showSuccess(t`Successfully Updated Social Links`);
            },
            onError: (error) => {
                formErrorHandle(form, error);
            }
        });
    }

    const primaryPlatforms = socialPlatforms.filter(p => p.priority === 'primary');
    const secondaryPlatforms = socialPlatforms.filter(p => p.priority === 'secondary');

    const visiblePlatforms = showMore ? [...primaryPlatforms, ...secondaryPlatforms] : primaryPlatforms;

    return (
        <SettingsPage
            title={t`Social Links`}
            description={t`Add your social media handles and website URL. These will be displayed on your public organizer page.`}
        >
            <SettingsSection>
                <form onSubmit={form.onSubmit(handleSubmit)}>
                    <fieldset disabled={organizerSettingsQuery.isLoading || updateMutation.isPending}>
                        {visiblePlatforms.map((platform) => {
                            const Icon = platform.icon;
                            const inputId = `organizer-social-${platform.field}`;
                            return (
                                <SettingsRow key={platform.field} label={platform.name} htmlFor={inputId}>
                                    <TextInput
                                        id={inputId}
                                        {...form.getInputProps(platform.field)}
                                        placeholder={platform.placeholder}
                                        leftSection={<Icon size={18}/>}
                                    />
                                </SettingsRow>
                            );
                        })}
                        <SettingsBlock>
                            <Button
                                variant="subtle"
                                color="gray"
                                fullWidth
                                leftSection={showMore ? <IconChevronUp size={18}/> : <IconChevronDown size={18}/>}
                                onClick={() => setShowMore(!showMore)}
                            >
                                {showMore
                                    ? t`Show fewer platforms`
                                    : hasSecondaryValues
                                        ? t`Show all platforms (${secondaryPlatforms.filter(p => form.values[p.field]).length} more with values)`
                                        : t`Show more platforms`
                                }
                            </Button>
                        </SettingsBlock>
                    </fieldset>
                    <SettingsSaveFooter
                        isDirty={form.isDirty()}
                        isSaving={updateMutation.isPending}
                        onDiscard={form.reset}
                    />
                </form>
            </SettingsSection>
        </SettingsPage>
    );
}
