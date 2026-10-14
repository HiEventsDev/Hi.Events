import {useRef, useState} from "react";
import {Navigate} from "react-router";
import {Alert, Container, Group, Skeleton, Stack, Text, Title, UnstyledButton} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconInfoCircle} from "@tabler/icons-react";
import {LifecycleNudgeType} from "../../../../api/admin.client";
import {useGetAdminLifecycleEmails} from "../../../../queries/useGetAdminLifecycleEmails";
import {getLifecycleEmailLabel} from "./lifecycleEmailLabels";
import {LifecycleEmailEditor} from "./LifecycleEmailEditor";
import classes from "./LifecycleEmails.module.scss";

const LifecycleEmails = () => {
    const {data, isLoading, isError} = useGetAdminLifecycleEmails();
    const [selectedType, setSelectedType] = useState<LifecycleNudgeType | null>(null);
    const isDirtyRef = useRef(false);

    const emails = data?.data ?? [];
    const selected = emails.find((email) => email.nudge_type === selectedType) ?? emails[0];

    if (isError) {
        return <Navigate to="/admin" replace/>;
    }

    const handleSelect = (nudgeType: LifecycleNudgeType) => {
        if (nudgeType === selected?.nudge_type) {
            return;
        }
        if (isDirtyRef.current && !window.confirm(t`Discard your unsaved changes?`)) {
            return;
        }
        isDirtyRef.current = false;
        setSelectedType(nudgeType);
    };

    return (
        <Container size="xl" p="xl" fluid>
            <Stack gap="lg">
                <Stack gap={4}>
                    <Title order={1}>{t`Lifecycle Emails`}</Title>
                    <Text c="dimmed">
                        {t`Automatic emails that help organizers who get stuck. The sweep runs hourly and only reaches activity that happens after an email is turned on.`}
                    </Text>
                </Stack>

                {data && !data.meta.sending_configured && (
                    <Alert color="yellow" variant="light" icon={<IconInfoCircle size={18}/>}>
                        {t`Sending is off because no lifecycle mailer is configured. You can still edit and preview templates.`}
                    </Alert>
                )}

                {isLoading || !selected ? (
                    <div className={classes.layout}>
                        <Stack gap="sm">
                            <Skeleton height={96} radius="md"/>
                            <Skeleton height={96} radius="md"/>
                            <Skeleton height={96} radius="md"/>
                        </Stack>
                        <Skeleton height={640} radius="md"/>
                    </div>
                ) : (
                    <div className={classes.layout}>
                        <nav className={classes.list}>
                            {emails.map((email) => {
                                const label = getLifecycleEmailLabel(email.nudge_type);
                                const isActive = email.nudge_type === selected.nudge_type;
                                return (
                                    <UnstyledButton
                                        key={email.nudge_type}
                                        className={`${classes.listItem} ${isActive ? classes.listItemActive : ''}`}
                                        onClick={() => handleSelect(email.nudge_type)}
                                        data-testid={`lifecycle-email-item-${email.nudge_type}`}
                                    >
                                        <Stack gap={6}>
                                            <Group gap="xs" wrap="nowrap">
                                                <span className={`${classes.statusDot} ${email.enabled_at ? classes.statusDotLive : ''}`}/>
                                                <Text fw={600} size="sm">{label.name}</Text>
                                            </Group>
                                            <Text size="xs" c="dimmed" lineClamp={2}>{label.trigger}</Text>
                                            <Text size="xs" c="dimmed">
                                                {t`${email.sent_total} sent · ${email.sent_last_30_days} in the last 30 days`}
                                            </Text>
                                            {email.sent_total > 0 && (
                                                <Text size="xs" c="dimmed" data-testid={`lifecycle-email-delivery-${email.nudge_type}`}>
                                                    {t`${email.delivered_total} delivered · ${email.opened_total} opened · ${email.clicked_total} clicked · ${email.bounced_total} bounced`}
                                                </Text>
                                            )}
                                        </Stack>
                                    </UnstyledButton>
                                );
                            })}
                        </nav>

                        <LifecycleEmailEditor
                            key={selected.nudge_type}
                            email={selected}
                            sendingConfigured={data?.meta.sending_configured ?? false}
                            onDirtyChange={(isDirty) => {
                                isDirtyRef.current = isDirty;
                            }}
                        />
                    </div>
                )}
            </Stack>
        </Container>
    );
};

export default LifecycleEmails;
