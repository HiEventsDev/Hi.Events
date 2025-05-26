import {useLoaderData} from "react-router";
import {Container, SimpleGrid, Skeleton} from '@mantine/core';
import {EventCard} from './EventCard';
import classes from './PublicOrganizer.module.scss';
import {Calendar} from "@mantine/dates";
import {useMemo, useState} from 'react';
import {useGetOrganizerPublic} from "../../../queries/useGetOrganizerPublic.ts";

const PublicOrganizer = () => {
    const {organizerId} = useLoaderData() as { organizerId: string };
    const {data: organizer, isLoading} = useGetOrganizerPublic(organizerId);
    const [sortOrder, setSortOrder] = useState<'newest' | 'oldest'>('newest');

    const sortedEvents = useMemo(() => {
        if (!organizer?.events) return [];

        return [...organizer.events].sort((a, b) => {
            const dateA = new Date(a.start_date).getTime();
            const dateB = new Date(b.start_date).getTime();
            return sortOrder === 'newest' ? dateB - dateA : dateA - dateB;
        });
    }, [organizer?.events, sortOrder]);

    const toggleSortOrder = () => {
        setSortOrder(prev => prev === 'newest' ? 'oldest' : 'newest');
    };

    const organizerLogo = organizer?.images?.find((image) => image.type === 'ORGANIZER_LOGO');
    const organizerCover = organizer?.images?.find((image) => image.type === 'ORGANIZER_COVER');

    // Demo events for when no real events exist
    const demoEvents = [
        {
            id: 'demo',
            slug: 'ai-disrupt-summit',
            title: 'AI Disrupt Summit | Build Club',
            description_preview: 'Join us for an exciting summit on AI disruption and innovation.',
            start_date: '2025-05-26T12:00:00Z',
            currency: 'AUD',
            timezone: 'Australia/Sydney',
            status: 'LIVE' as const,
            organizer: {
                id: '1',
                name: 'Build Club',
                slug: 'build-club'
            },
            location_details: {
                city: 'Sydney',
                venue_name: 'Innovation Hub'
            },
        },
        {
            id: 'demo2',
            slug: 'ai-workshop-basics',
            title: 'AI Workshop: Basics | Build Club',
            description_preview: 'Learn the fundamentals of AI in this hands-on workshop.',
            start_date: '2025-05-20T10:00:00Z',
            currency: 'AUD',
            timezone: 'Australia/Sydney',
            status: 'LIVE' as const,
            organizer: {
                id: '1',
                name: 'Build Club',
                slug: 'build-club'
            },
            location_details: {
                city: 'Sydney',
                venue_name: 'Innovation Hub'
            },
        }
    ];

    if (isLoading) {
        return (
            <Container size="lg" className={classes.container}>
                <Skeleton height={200} mb="lg"/>
                <Skeleton height={40} mb="md"/>
                <Skeleton height={100} mb="lg"/>
                <SimpleGrid cols={{base: 1, md: 2}}>
                    <Skeleton height={200}/>
                    <Skeleton height={200}/>
                </SimpleGrid>
            </Container>
        );
    }

    const eventsToShow = sortedEvents.length > 0 ? sortedEvents : demoEvents;

    return (
        <main className={classes.container}>
            <div className={classes.wrapper}>
                <header className={classes.header}>
                    <div className={classes.coverImageAndLogo}>
                        <div className={classes.coverImage}>
                            <img
                                src={organizerCover.url}
                                alt="Cover"
                            />
                        </div>
                        <div className={classes.logo}>
                            <img
                                src={organizerLogo.url}
                                alt="Logo"
                            />
                        </div>
                    </div>
                    <div className={classes.organizerInfo}>
                        <h1>{organizer.name}</h1>
                        <div className={classes.organizerDetails}>
                            <span className={classes.location}>🕐 Sydney — 9:55 a.m. AEST</span>
                            <p className={classes.description}>
                                {organizer.description}
                            </p>
                        </div>
                    </div>
                </header>

                <div className={classes.content}>
                    <div className={classes.eventsList}>
                        <div className={classes.eventsHeader}>
                            <h2>Events</h2>
                            <div className={classes.eventsControls}>
                                <button
                                    className={classes.sortToggle}
                                    onClick={toggleSortOrder}
                                >
                                    {sortOrder === 'newest' ? 'Show Oldest First' : 'Show Newest First'}
                                </button>
                            </div>
                        </div>

                        <div className={classes.eventsContainer}>
                            {eventsToShow.map((event) => (
                                <EventCard key={event.id} event={event}/>
                            ))}
                        </div>
                    </div>

                    <aside className={classes.sidebar}>
                        <div className={classes.subscribeSection}>
                            <button className={classes.subscribeButton}>
                                <svg
                                    className={classes.rssIcon}
                                    viewBox="0 0 24 24"
                                    fill="currentColor"
                                >
                                    <path d="M4 11a9 9 0 0 1 9 9"></path>
                                    <path d="M4 4a16 16 0 0 1 16 16"></path>
                                    <circle cx="5" cy="19" r="1"></circle>
                                </svg>
                                Subscribe
                            </button>
                        </div>
                        <div className={classes.calendar}>
                            <Calendar/>
                        </div>
                    </aside>
                </div>
            </div>
        </main>
    );
};

export default PublicOrganizer;
