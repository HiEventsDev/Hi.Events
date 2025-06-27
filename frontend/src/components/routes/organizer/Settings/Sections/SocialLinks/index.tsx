import {useParams} from "react-router";
import {useForm} from "@mantine/form";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useEffect, useMemo, useState} from "react";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";
import {Card} from "../../../../../common/Card";
import {HeadingWithDescription} from "../../../../../common/Card/CardHeading";
import {Button, Collapse, Group, Text, TextInput, UnstyledButton} from "@mantine/core";
import {useGetOrganizerSettings} from "../../../../../../queries/useGetOrganizerSettings.ts";
import {useUpdateOrganizerSettings} from "../../../../../../mutations/useUpdateOrganizerSettings.ts";
import {
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
import {InputGroup} from "../../../../../common/InputGroup";

interface SocialPlatform {
    name: string;
    field: string;
    icon: any;
    placeholder: string;
    priority?: 'primary' | 'secondary';
}

const socialPlatforms: SocialPlatform[] = [
    // Primary platforms (always visible)
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

    // Secondary platforms (collapsible)
    {name: t`TikTok`, field: 'tiktok_handle', icon: IconBrandTiktok, placeholder: 'username', priority: 'primary'},
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

            // Handle website URL
            if (organizerSettingsQuery.data.website_url) {
                formValues.website_url = organizerSettingsQuery.data.website_url;
            }

            // Handle social media handles
            if (organizerSettingsQuery.data.social_media_handles) {
                socialPlatforms.forEach(platform => {
                    if (platform.field !== 'website_url') {
                        const handle = platform.field.replace('_handle', '');
                        if (organizerSettingsQuery.data.social_media_handles[handle]) {
                            formValues[platform.field] = organizerSettingsQuery.data.social_media_handles[handle];
                        }
                    }
                });
            }

            form.setValues(formValues);
        }
    }, [organizerSettingsQuery.isFetched]);

    // Check if any secondary platforms have values
    const hasSecondaryValues = useMemo(() => {
        return socialPlatforms
            .filter(p => p.priority === 'secondary')
            .some(platform => form.values[platform.field] && form.values[platform.field].trim() !== '');
    }, [form.values]);

    // Auto-expand if secondary platforms have values
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
                showSuccess(t`Successfully Updated Social Links`);
            },
            onError: (error) => {
                formErrorHandle(form, error);
            }
        });
    }

    const primaryPlatforms = socialPlatforms.filter(p => p.priority === 'primary');
    const secondaryPlatforms = socialPlatforms.filter(p => p.priority === 'secondary');

    return (
        <Card>
            <HeadingWithDescription
                heading={t`Social Links & Website`}
                description={t`Add your social media handles and website URL. These will be displayed on your public organizer page.`}
            />
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <fieldset disabled={organizerSettingsQuery.isLoading || updateMutation.isPending}>
                    {/* Primary platforms - always visible */}
                    <InputGroup>
                        {primaryPlatforms.map((platform) => {
                            const Icon = platform.icon;
                            return (
                                <TextInput
                                    key={platform.field}
                                    {...form.getInputProps(platform.field)}
                                    label={platform.name}
                                    placeholder={platform.placeholder}
                                    leftSection={<Icon size={18}/>}
                                    type={platform.field === 'website_url' ? 'url' : 'text'}
                                />
                            );
                        })}
                    </InputGroup>

                    {/* Toggle button for secondary platforms */}
                    <UnstyledButton
                        onClick={() => setShowMore(!showMore)}
                        style={{
                            width: '100%',
                            padding: '12px',
                            marginTop: '20px',
                            marginBottom: '20px',
                            borderRadius: '8px',
                            backgroundColor: 'var(--mantine-color-gray-0)',
                            border: '1px solid var(--mantine-color-gray-3)',
                            transition: 'all 0.2s',
                        }}
                        onMouseEnter={(e) => {
                            e.currentTarget.style.backgroundColor = 'var(--mantine-color-gray-1)';
                        }}
                        onMouseLeave={(e) => {
                            e.currentTarget.style.backgroundColor = 'var(--mantine-color-gray-0)';
                        }}
                    >
                        <Group justify="center" gap="xs">
                            {showMore ? <IconChevronUp size={18}/> : <IconChevronDown size={18}/>}
                            <Text size="sm" fw={500}>
                                {showMore
                                    ? t`Show fewer platforms`
                                    : hasSecondaryValues
                                        ? t`Show all platforms (${secondaryPlatforms.filter(p => form.values[p.field]).length} more with values)`
                                        : t`Show more platforms`
                                }
                            </Text>
                        </Group>
                    </UnstyledButton>

                    {/* Secondary platforms - collapsible */}
                    <Collapse in={showMore}>
                        <InputGroup>
                            {secondaryPlatforms.map((platform) => {
                                const Icon = platform.icon;
                                return (
                                    <TextInput
                                        key={platform.field}
                                        {...form.getInputProps(platform.field)}
                                        label={platform.name}
                                        placeholder={platform.placeholder}
                                        leftSection={<Icon size={18}/>}
                                        type={platform.field === 'website_url' ? 'url' : 'text'}
                                    />
                                );
                            })}
                        </InputGroup>
                    </Collapse>

                    <Button
                        loading={updateMutation.isPending}
                        type={'submit'}
                        mt="xl"
                    >
                        {t`Save Social Links`}
                    </Button>
                </fieldset>
            </form>
        </Card>
    );
}
