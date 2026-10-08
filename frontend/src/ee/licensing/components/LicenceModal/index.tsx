import {Badge, Button, Code, Group, Modal, Stack, Text, ThemeIcon} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {ReactNode} from "react";
import {Link} from "react-router";
import {type Icon, IconClock, IconCode, IconExternalLink, IconKey, IconLock} from "@tabler/icons-react";
import {LicenceSummary} from "../../../../types.ts";
import {formatDateWithLocale} from "../../../../utilites/dates.ts";
import {LICENCE_TONE_COLORS, LicenceNotice, LicenceNoticeKind, licensedFeatureLabel, licensedFeatureList} from "../../licenceNotice.ts";
import {licensingUrl} from "../../licensingUrl.ts";
import {DevelopmentLicenceSummary} from "../DevelopmentLicenceSummary";
import classes from "./LicenceModal.module.scss";

interface LicenceModalProps {
    licence: LicenceSummary;
    notice: LicenceNotice;
    showDetailsLink: boolean;
    onClose: () => void;
}

interface LicenceNextSteps {
    title: string;
    items: ReactNode[];
    ordered: boolean;
}

interface LicenceModalContent {
    icon: Icon;
    title: string;
    body: ReactNode;
    nextSteps: LicenceNextSteps;
    cta?: { label: string, isPrimary: boolean };
    showFeaturesInUse: boolean;
}

const formatDate = (date: string | null): string => date ? formatDateWithLocale(date, 'shortDate', 'UTC') : '';

const redeployStep = () => <Trans>Redeploy the backend. With Docker Compose, run <Code>docker compose up -d</Code>.</Trans>;

const renewSteps = (): LicenceNextSteps => ({
    title: t`What to do`,
    ordered: true,
    items: [
        t`Renew your licence.`,
        <Trans>Put the new key in <Code>APP_LICENCE_KEY</Code> and redeploy the backend.</Trans>,
    ],
});

const lapsedBody = (featureList: string | null): string => featureList
    ? t`Setup for ${featureList} is locked. Existing sales and scheduled dates keep working, and anything already set up can still be viewed or removed.`
    : t`The "Powered by Hi.Events" notice is shown again until you renew.`;

const contentFor = (kind: LicenceNoticeKind, licence: LicenceSummary): LicenceModalContent => {
    const featureList = licensedFeatureList(licence.features_in_use);
    const expiresAt = formatDate(licence.expires_at);
    const graceEndsAt = formatDate(licence.grace_ends_at);

    switch (kind) {
        case 'invalid':
            return {
                icon: IconKey,
                title: t`Licence key not recognised`,
                body: <Trans><Code>APP_LICENCE_KEY</Code> is set, but the key couldn't be verified, so reserved seating and the box office stay locked.</Trans>,
                nextSteps: {
                    title: t`What to do`,
                    ordered: true,
                    items: [
                        t`Copy the key again from your licence email, without quotes or spaces.`,
                        <Trans>Set it as <Code>APP_LICENCE_KEY</Code> and redeploy the backend.</Trans>,
                        t`Still failing? Upgrade Hi.Events, then contact hello@hi.events.`,
                    ],
                },
                showFeaturesInUse: false,
            };
        case 'locked':
            return {
                icon: IconLock,
                title: t`No Enterprise licence`,
                body: t`Setup for ${featureList} is locked because this instance has no Hi.Events Enterprise licence. Existing sales keep working.`,
                nextSteps: {
                    title: t`What to do`,
                    ordered: true,
                    items: [
                        t`Get an Enterprise licence key.`,
                        <Trans>Set it as <Code>APP_LICENCE_KEY</Code> in the backend environment.</Trans>,
                        redeployStep(),
                    ],
                },
                cta: {label: t`Get a licence`, isPrimary: true},
                showFeaturesInUse: false,
            };
        case 'dev':
            return {
                icon: IconCode,
                title: t`Development licence`,
                body: <DevelopmentLicenceSummary/>,
                nextSteps: {
                    title: t`Before you go live`,
                    ordered: false,
                    items: [
                        <Trans>Not using reserved seating or the box office? Remove <Code>APP_LICENCE_KEY</Code> and redeploy.</Trans>,
                        <Trans>Using them for real events? Get an Enterprise licence and put its key in <Code>APP_LICENCE_KEY</Code>.</Trans>,
                    ],
                },
                cta: {label: t`Get a licence`, isPrimary: false},
                showFeaturesInUse: false,
            };
        case 'grace':
            return {
                icon: IconClock,
                title: t`Your licence has expired`,
                body: t`Your Hi.Events Enterprise licence expired on ${expiresAt}. Everything stays on until ${graceEndsAt}. Renew before then to avoid interruption.`,
                nextSteps: renewSteps(),
                cta: {label: t`Renew licence`, isPrimary: true},
                showFeaturesInUse: true,
            };
        case 'lapsed':
            return {
                icon: IconLock,
                title: t`Your licence has expired`,
                body: lapsedBody(featureList),
                nextSteps: renewSteps(),
                cta: {label: t`Renew licence`, isPrimary: true},
                showFeaturesInUse: false,
            };
    }
};

export const LicenceModal = ({licence, notice, showDetailsLink, onClose}: LicenceModalProps) => {
    const content = contentFor(notice.kind, licence);
    const HeaderIcon = content.icon;

    return (
        <Modal.Root opened onClose={onClose} centered size={460} radius="md">
            <Modal.Overlay backgroundOpacity={0.55} blur={3}/>
            <Modal.Content data-testid="licence-modal" data-kind={notice.kind}>
                <Modal.Header>
                    <Group gap="sm" wrap="nowrap">
                        <ThemeIcon size={36} radius="md" variant="light" color={LICENCE_TONE_COLORS[notice.tone]}>
                            <HeaderIcon size={20}/>
                        </ThemeIcon>
                        <Modal.Title fw={600}>{content.title}</Modal.Title>
                    </Group>
                    <Modal.CloseButton/>
                </Modal.Header>
                <Modal.Body>
                    <Stack gap="md">
                        <Text size="sm">{content.body}</Text>

                        {notice.kind === 'invalid' && licence.invalid_reason && (
                            <div className={classes.reason}>{licence.invalid_reason}</div>
                        )}

                        {content.showFeaturesInUse && (
                            <Group gap={6}>
                                <Text size="xs" c="dimmed">{t`In use`}</Text>
                                {licence.features_in_use.map((feature) => (
                                    <Badge key={feature} variant="outline" color="gray" size="sm">
                                        {licensedFeatureLabel(feature)}
                                    </Badge>
                                ))}
                            </Group>
                        )}

                        <div className={classes.steps}>
                            <Text size="xs" c="dimmed" fw={500}>{content.nextSteps.title}</Text>
                            <ol className={classes.stepList}>
                                {content.nextSteps.items.map((step, index) => (
                                    <li key={index} className={classes.step}>
                                        {content.nextSteps.ordered
                                            ? <span className={classes.stepNumber}>{index + 1}</span>
                                            : <span className={classes.stepBullet}/>}
                                        <span>{step}</span>
                                    </li>
                                ))}
                            </ol>
                        </div>

                        {(showDetailsLink || content.cta) && (
                            <Group justify="flex-end" gap="xs">
                                {showDetailsLink && (
                                    <Button variant="default" component={Link} to="/admin/licence" onClick={onClose}>
                                        {t`Licence details`}
                                    </Button>
                                )}
                                {content.cta && (
                                    <Button
                                        component="a"
                                        href={licensingUrl('app-licence-modal')}
                                        target="_blank"
                                        rel="noopener"
                                        variant={content.cta.isPrimary ? 'filled' : 'default'}
                                        rightSection={<IconExternalLink size={16}/>}
                                    >
                                        {content.cta.label}
                                    </Button>
                                )}
                            </Group>
                        )}
                    </Stack>
                </Modal.Body>
            </Modal.Content>
        </Modal.Root>
    );
};
