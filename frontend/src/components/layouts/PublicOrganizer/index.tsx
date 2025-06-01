import {useLoaderData} from "react-router";
import {ActionIcon, Button, Container, Group, Modal, Textarea, TextInput} from '@mantine/core';
import {EventCard} from './EventCard';
import classes from './PublicOrganizer.module.scss';
import React, {useEffect, useMemo, useState} from 'react';
import {Event, Organizer, QueryFilterOperator} from "../../../types.ts";
import {useForm} from "@mantine/form";
import {useGetOrganizerPublicEvents} from "../../../queries/useGetOrganizerEventsPublic.ts";
import {OrganizerDocumentHead} from "../../common/OrganizerDocumentHead";
import {
    IconArrowRight,
    IconBrandDiscord,
    IconBrandFacebook,
    IconBrandFlickr,
    IconBrandGithub,
    IconBrandInstagram,
    IconBrandLinkedin,
    IconBrandPinterest,
    IconBrandReddit,
    IconBrandSnapchat,
    IconBrandTelegram,
    IconBrandTiktok,
    IconBrandTumblr,
    IconBrandTwitch,
    IconBrandVimeo,
    IconBrandVk,
    IconBrandWechat,
    IconBrandWeibo,
    IconBrandWhatsapp,
    IconBrandX,
    IconBrandYoutube,
    IconMail,
    IconWorld
} from '@tabler/icons-react';
import {t} from "@lingui/macro";
import {PoweredByFooter} from "../../common/PoweredByFooter";

interface PublicOrganizerProps {
    previewData?: Organizer;
    isPreview?: boolean;
}

const socialMediaConfig = {
    facebook: {icon: IconBrandFacebook, baseUrl: 'https://facebook.com/'},
    instagram: {icon: IconBrandInstagram, baseUrl: 'https://instagram.com/'},
    twitter: {icon: IconBrandX, baseUrl: 'https://twitter.com/'},
    linkedin: {icon: IconBrandLinkedin, baseUrl: 'https://linkedin.com/in/'},
    discord: {icon: IconBrandDiscord, baseUrl: 'https://discord.com/users/'},
    tiktok: {icon: IconBrandTiktok, baseUrl: 'https://tiktok.com/@'},
    youtube: {icon: IconBrandYoutube, baseUrl: 'https://youtube.com/@'},
    snapchat: {icon: IconBrandSnapchat, baseUrl: 'https://snapchat.com/add/'},
    twitch: {icon: IconBrandTwitch, baseUrl: 'https://twitch.tv/'},
    reddit: {icon: IconBrandReddit, baseUrl: 'https://reddit.com/u/'},
    pinterest: {icon: IconBrandPinterest, baseUrl: 'https://pinterest.com/'},
    whatsapp: {icon: IconBrandWhatsapp, baseUrl: 'https://wa.me/'},
    telegram: {icon: IconBrandTelegram, baseUrl: 'https://t.me/'},
    vk: {icon: IconBrandVk, baseUrl: 'https://vk.com/'},
    weibo: {icon: IconBrandWeibo, baseUrl: 'https://weibo.com/'},
    wechat: {icon: IconBrandWechat, baseUrl: '#'}, // WeChat doesn't have direct URLs
    flickr: {icon: IconBrandFlickr, baseUrl: 'https://flickr.com/people/'},
    tumblr: {icon: IconBrandTumblr, baseUrl: 'https://tumblr.com/blog/'},
    // quora: { icon: IconBrandQuora, baseUrl: 'https://quora.com/profile/' },
    vimeo: {icon: IconBrandVimeo, baseUrl: 'https://vimeo.com/'},
    github: {icon: IconBrandGithub, baseUrl: 'https://github.com/'},
};

export const PublicOrganizer = ({previewData, isPreview}: PublicOrganizerProps) => {
    const loaderData = useLoaderData() as {
        organizerId: string;
        organizer: Organizer | null;
        upcomingEventsData: any;
    } | undefined;

    const organizerId = loaderData?.organizerId;
    const organizer = previewData || loaderData?.organizer;
    const upcomingEventsFromLoader = previewData?.events || loaderData?.upcomingEventsData;

    const [eventFilter, setEventFilter] = useState<'upcoming' | 'past'>('upcoming');
    const [upcomingPage, setUpcomingPage] = useState(1);
    const [pastPage, setPastPage] = useState(1);
    const [contactModalOpen, setContactModalOpen] = useState(false);

    const currentDate = useMemo(() => new Date().toISOString(), []);

    // Only fetch past events when selected
    const pastQueryFilters = useMemo(() => ({
        pageNumber: pastPage,
        perPage: 25,
        sortBy: 'start_date',
        sortDirection: 'desc' as const, // Most recent past events first
        filterFields: {
            start_date: {
                operator: QueryFilterOperator.LessThan,
                value: currentDate
            }
        }
    }), [pastPage, currentDate]);

    // Only fetch more upcoming events when paginating
    const upcomingQueryFilters = useMemo(() => ({
        pageNumber: upcomingPage,
        perPage: 25,
        sortBy: 'start_date',
        sortDirection: 'asc' as const,
        filterFields: {
            start_date: {
                operator: QueryFilterOperator.GreaterThanOrEquals,
                value: currentDate
            }
        }
    }), [upcomingPage, currentDate]);

    // Fetch past events only when past tab is selected
    const {data: pastEventsData} = useGetOrganizerPublicEvents(
        organizerId!,
        pastQueryFilters,
        {
            enabled: !!organizerId && !isPreview && eventFilter === 'past'
        }
    );

    // Fetch more upcoming events only when paginating beyond initial load
    const {data: moreUpcomingEventsData} = useGetOrganizerPublicEvents(
        organizerId!,
        upcomingQueryFilters,
        {
            enabled: !!organizerId && !isPreview && eventFilter === 'upcoming' && upcomingPage > 1
        }
    );

    // Use loader data for initial upcoming events, then paginated data
    const eventsData = eventFilter === 'upcoming'
        ? (upcomingPage === 1 ? upcomingEventsFromLoader : moreUpcomingEventsData)
        : pastEventsData;

    useEffect(() => {
        if (eventFilter === 'upcoming') {
            setUpcomingPage(1);
        } else {
            setPastPage(1);
        }
    }, [eventFilter]);

    const contactForm = useForm({
        initialValues: {
            name: '',
            email: '',
            subject: '',
            message: '',
        },
        validate: {
            email: (value) => (/^\S+@\S+$/.test(value) ? null : 'Invalid email'),
            name: (value) => (value.length < 2 ? 'Name must have at least 2 letters' : null),
            message: (value) => (value.length < 10 ? 'Message must be at least 10 characters' : null),
        },
    });

    const handleFilterChange = (filter: 'upcoming' | 'past') => {
        setEventFilter(filter);
    };

    const handleNextPage = () => {
        if (eventFilter === 'upcoming') {
            setUpcomingPage(prev => prev + 1);
        } else {
            setPastPage(prev => prev + 1);
        }
    };

    const handleContactSubmit = (values: typeof contactForm.values) => {
        // TODO: Implement contact form submission
        console.log('Contact form submitted:', values);
        setContactModalOpen(false);
        contactForm.reset();
    };

    const organizerLogo = organizer?.images?.find((image) => image.type === 'ORGANIZER_LOGO');
    const organizerCover = organizer?.images?.find((image) => image.type === 'ORGANIZER_COVER');

    // Get social media links that have values
    const socialLinks = organizer?.settings?.social_media_handles ? Object.entries(organizer.settings.social_media_handles)
        .filter(([_, handle]) => handle && handle.trim())
        .map(([platform, handle]) => ({
            platform,
            handle: handle!,
            config: socialMediaConfig[platform as keyof typeof socialMediaConfig]
        }))
        .filter(item => item.config) : [];

    const websiteUrl = organizer?.settings?.website_url;

    // Since we're using SSR, we should have data from the loader
    // Only show loading state if we're in preview mode and don't have data
    if (!organizer && !isPreview) {
        return (
            <Container size="lg" className={classes.container}>
                <div>Organizer not found</div>
            </Container>
        );
    }

    const events = eventsData?.data || eventsData || [];

    const hasMorePages = eventsData?.meta &&
        (eventFilter === 'upcoming' ? upcomingPage : pastPage) < eventsData.meta.last_page;

    // Apply theme settings if available
    const themeSettings = organizer?.settings?.homepage_theme_settings;
    const themeStyles = themeSettings ? {
        '--organizer-bg-color': themeSettings.homepage_background_color || '#f5f5f5',
        '--organizer-content-bg-color': themeSettings.homepage_content_background_color || '#ffffff',
        '--organizer-primary-color': themeSettings.homepage_primary_color || '#8b5cf6',
        '--organizer-primary-text-color': themeSettings.homepage_primary_text_color || '#1a1a1a',
        '--organizer-secondary-color': themeSettings.homepage_secondary_color || '#6366f1',
        '--organizer-secondary-text-color': themeSettings.homepage_secondary_text_color || '#6b7280',
    } as React.CSSProperties : {};

    return (
        <>
            {organizer && <OrganizerDocumentHead organizer={organizer} />}
            <main className={classes.container} style={themeStyles}>
                <style>
                    {`
                        body, .ssr-loader {
                            background-color: ${themeSettings?.homepage_background_color || '#f5f5f5'} !important;
                        }
                    `}
                </style>
                <div className={classes.wrapper}>
                <header className={classes.header}>
                    {organizerCover && (
                        <div className={classes.coverImage}>
                            <img
                                src={organizerCover.url}
                                alt="Cover"
                            />
                        </div>
                    )}
                    <div className={classes.organizerContent}>
                        {organizerLogo && (
                            <div className={classes.logo}>
                                <img
                                    src={organizerLogo.url}
                                    alt="Logo"
                                />
                            </div>
                        )}
                        <div className={classes.organizerInfo}>
                            <h1>{organizer?.name}</h1>
                            <div className={classes.organizerDetails}>
                                {organizer?.settings?.location_details?.city && (
                                    <span
                                        className={classes.location}>📍 {organizer.settings.location_details.city}</span>
                                )}
                                <p className={classes.description}
                                   dangerouslySetInnerHTML={organizer?.description ? {__html: organizer.description} : {__html: ''}}/>

                                {/* Social Links and Contact */}
                                <div className={classes.organizerActions}>
                                    {(socialLinks.length > 0 || websiteUrl) && (
                                        <div className={classes.socialLinks}>
                                            {websiteUrl && (
                                                <ActionIcon
                                                    component="a"
                                                    href={websiteUrl}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className={classes.socialIcon}
                                                    size="lg"
                                                >
                                                    <IconWorld size={20}/>
                                                </ActionIcon>
                                            )}
                                            {socialLinks.map(({platform, handle, config}) => {
                                                const IconComponent = config.icon;
                                                const url = config.baseUrl + handle;
                                                return (
                                                    <ActionIcon
                                                        key={platform}
                                                        component="a"
                                                        href={url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className={classes.socialIcon}
                                                        size="lg"
                                                    >
                                                        <IconComponent size={20}/>
                                                    </ActionIcon>
                                                );
                                            })}
                                        </div>
                                    )}

                                    <Button
                                        leftSection={<IconMail size={16}/>}
                                        onClick={() => setContactModalOpen(true)}
                                        className={classes.contactButton}
                                        variant="outline"
                                    >
                                        {t`Contact Organizer`}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </header>

                <div className={classes.content}>
                    <div className={classes.eventsHeader}>
                        <h2>{t`Events`}</h2>
                        <div className={classes.eventsControls}>
                            <Button.Group>
                                <Button
                                    variant={eventFilter === 'upcoming' ? 'filled' : 'default'}
                                    onClick={() => handleFilterChange('upcoming')}
                                    size="sm"
                                    style={{
                                        backgroundColor: eventFilter === 'upcoming' ? themeSettings?.homepage_secondary_color : 'transparent',
                                        color: eventFilter === 'upcoming'
                                            ? themeSettings?.homepage_secondary_text_color
                                            : themeSettings?.homepage_primary_color,
                                        borderColor: themeSettings?.homepage_secondary_color,
                                    }}
                                >
                                    {t`Upcoming`}
                                </Button>
                                <Button
                                    variant={eventFilter === 'past' ? 'filled' : 'default'}
                                    onClick={() => handleFilterChange('past')}
                                    size="sm"
                                    style={{
                                        backgroundColor: eventFilter === 'past' ? themeSettings?.homepage_secondary_color : 'transparent',
                                        color: eventFilter === 'past'
                                            ? themeSettings?.homepage_secondary_text_color
                                            : themeSettings?.homepage_primary_color,
                                        borderColor: themeSettings?.homepage_secondary_color,
                                    }}
                                >
                                    {t`Past`}
                                </Button>
                            </Button.Group>
                        </div>
                    </div>

                    <div className={classes.eventsContainer}>
                        {events.length === 0 ? (
                            <div className={classes.noEvents}>
                                <p>{eventFilter === 'upcoming' ? t`No upcoming events` : t`No past events`}</p>
                            </div>
                        ) : (
                            events.map((event) => (
                                <EventCard
                                    key={event.id}
                                    event={event as Event}
                                    primaryColor={themeSettings?.homepage_primary_color || '#8b5cf6'}
                                />
                            ))
                        )}
                    </div>

                    {hasMorePages && (
                        <div className={classes.loadMoreContainer}>
                            <Button
                                onClick={handleNextPage}
                                rightSection={<IconArrowRight size={16}/>}
                                size="lg"
                                className={classes.loadMoreButton}
                                style={{
                                    background: themeSettings?.homepage_primary_color || 'var(--primary-color)',
                                }}
                            >
                                {t`Show More Events`}
                            </Button>
                        </div>
                    )}
                </div>
                <PoweredByFooter className={classes.poweredBy}/>
            </div>

            {/* Contact Modal */}
            <Modal
                opened={contactModalOpen}
                onClose={() => setContactModalOpen(false)}
                title={t`Contact ${organizer?.name || 'Organizer'}`}
                size="md"
                className={classes.contactModal}
            >
                <form onSubmit={contactForm.onSubmit(handleContactSubmit)}>
                    <Group grow mb="md">
                        <TextInput
                            label={t`Your Name`}
                            placeholder={t`Enter your name`}
                            required
                            {...contactForm.getInputProps('name')}
                        />
                        <TextInput
                            label={t`Your Email`}
                            placeholder={t`Enter your email`}
                            required
                            type="email"
                            {...contactForm.getInputProps('email')}
                        />
                    </Group>

                    <TextInput
                        label={t`Subject`}
                        placeholder={t`What is this about?`}
                        mb="md"
                        {...contactForm.getInputProps('subject')}
                    />

                    <Textarea
                        label={t`Message`}
                        placeholder={t`Write your message here...`}
                        required
                        minRows={4}
                        mb="md"
                        {...contactForm.getInputProps('message')}
                    />

                    <Group justify="flex-end">
                        <Button
                            variant="subtle"
                            onClick={() => setContactModalOpen(false)}
                        >
                            {t`Cancel`}
                        </Button>
                        <Button
                            type="submit"
                            className={classes.submitButton}
                        >
                            {t`Send Message`}
                        </Button>
                    </Group>
                </form>
            </Modal>
        </main>
        </>
    );
};

export default PublicOrganizer;
