import React from 'react';
import {Box, Button, Container, Image, rem, Stack, Text, Title} from '@mantine/core';
import {IconHome} from '@tabler/icons-react';
import classes from './GenericErrorPage.module.scss';
import {PoweredByFooter} from "../PoweredByFooter";
import {Helmet} from "react-helmet-async";
import {getConfig} from "../../../utilites/config.ts";

interface GenericErrorPageProps {
    title: string;
    description: string;
    pageTitle?: string;
    metaDescription?: string;
    buttonText?: string;
    buttonUrl?: string;
    buttonIcon?: React.ReactNode;
    children?: React.ReactNode;
}

export const GenericErrorPage: React.FC<GenericErrorPageProps> = ({
                                                                      title,
                                                                      description,
                                                                      pageTitle,
                                                                      metaDescription,
                                                                      buttonText,
                                                                      buttonUrl,
                                                                      buttonIcon = <IconHome size={18}/>,
                                                                      children
                                                                  }) => {
    return (
        <>
            <Helmet
                title={pageTitle || title}
                meta={[
                    {
                        name: 'description',
                        content: metaDescription || description,
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
                            src={getConfig("VITE_APP_LOGO_DARK", "/logo-dark.svg")}
                            alt={getConfig("VITE_APP_NAME", "Hi.Events") + " Logo"}
                            w={rem(140)}
                            h="auto"
                            fit="contain"
                            className={classes.logo}
                        />

                        <Stack gap="lg" align="center" className={classes.content}>
                            <Title order={1} className={classes.title}>
                                {title}
                            </Title>

                            <Text size="lg" c="dimmed" className={classes.description}>
                                {description}
                            </Text>

                            {children}

                            {buttonText && buttonUrl && (
                                <Button
                                    component="a"
                                    href={buttonUrl}
                                    leftSection={buttonIcon}
                                    variant="gradient"
                                    gradient={{from: 'purple', to: 'pink'}}
                                    className={classes.button}
                                >
                                    {buttonText}
                                </Button>
                            )}
                        </Stack>

                        <PoweredByFooter/>
                    </Stack>
                </Container>
            </Box>
        </>
    );
};

export default GenericErrorPage;
