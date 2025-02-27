import {t} from '@lingui/macro';
import {Box, Button, Container, Image, rem, Stack, Text, Title} from '@mantine/core';
import {IconHome} from '@tabler/icons-react';
import classes from './EventNotAvailable.module.scss';
import {PoweredByFooter} from "../../../common/PoweredByFooter";
import {Helmet} from "react-helmet-async";

export const EventNotAvailable = () => {
    return (
        <>
            <Helmet
                title={t`Event Not Available`}
                meta={[
                    {
                        name: 'description',
                        content: t`The event you're looking for is not available at the moment. It may have been removed, expired, or the URL might be incorrect.`,
                    },
                ]}
            />
            <Box className={classes.wrapper}>

                {/* Animated background elements */}
                <div className={classes.backgroundOrb1}/>
                <div className={classes.backgroundOrb2}/>

                <Container size="md" className={classes.root}>
                    <Stack gap="xl" align="center">
                        <Image
                            src="/logo-dark.svg"
                            alt="Logo"
                            w={rem(140)}
                            h="auto"
                            fit="contain"
                            className={classes.logo}
                        />

                        <Stack gap="lg" align="center" className={classes.content}>
                            <Title order={1} className={classes.title}>
                                {t`Event Not Available`}
                            </Title>

                            <Text size="lg" c="dimmed" className={classes.description}>
                                {t`The event you're looking for is not available at the moment. It may have been removed, expired, or the URL might be incorrect.`}
                            </Text>
                            <Button
                                component="a"
                                href="https://hi.events?utm_source=app.hi.events&utm_content=event-not-available"
                                leftSection={<IconHome size={18}/>}
                                variant="gradient"
                                gradient={{from: 'purple', to: 'pink'}}
                                className={classes.button}
                            >
                                {t`Go to Hi.Events`}
                            </Button>
                        </Stack>

                        <PoweredByFooter/>
                    </Stack>
                </Container>
            </Box>
        </>

    );
};

export default EventNotAvailable;
