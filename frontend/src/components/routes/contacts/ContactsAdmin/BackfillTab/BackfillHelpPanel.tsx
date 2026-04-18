import {t, Trans} from "@lingui/macro";
import {ActionIcon, Collapse, Group, Paper, Text, Title, Tooltip} from "@mantine/core";
import {IconChevronDown, IconChevronRight, IconInfoCircle} from "@tabler/icons-react";
import {useState} from "react";

export const BackfillHelpPanel = () => {
    const [open, setOpen] = useState(true);

    return (
        <Paper withBorder radius="md" p="md" bg="var(--mantine-color-blue-0)">
            <Group justify="space-between" align="center" wrap="nowrap">
                <Group gap="xs" align="center">
                    <IconInfoCircle size={18} color="var(--mantine-color-blue-7)"/>
                    <Title order={5}>{t`About Sync`}</Title>
                </Group>
                <Tooltip label={open ? t`Collapse` : t`Expand`}>
                    <ActionIcon variant="subtle" color="gray" onClick={() => setOpen((v) => !v)} aria-label={open ? t`Collapse` : t`Expand`}>
                        {open ? <IconChevronDown size={18}/> : <IconChevronRight size={18}/>}
                    </ActionIcon>
                </Tooltip>
            </Group>

            <Collapse in={open}>
                <Text size="sm" mb="xs" mt="sm">
                    <Trans>
                        Your contacts library unifies attendees across events. When you add a new contact
                        attribute (like "Dietary preference") or start using contacts mid-way through your
                        account's history, answers from past events don't retroactively flow in. Sync
                        reconciles that history.
                    </Trans>
                </Text>
                <Text size="sm" mb="xs">
                    <Trans>Three things to review and sync, one per sub-tab:</Trans>
                </Text>
                <Text size="sm" mb="xs">
                    <strong><Trans>New Contacts</Trans></strong>
                    {' — '}
                    <Trans>
                        Attendees from past orders whose email isn't in your contacts library yet. Select
                        the rows you want and click Add to create contacts for them; click Ignore to hide
                        them from future previews.
                    </Trans>
                </Text>
                <Text size="sm" mb="xs">
                    <strong><Trans>New Questions</Trans></strong>
                    {' — '}
                    <Trans>
                        Registration questions used on past events that aren't marked as reusable. Select
                        the rows and click Reuse to create an extended attribute from each question and
                        link them; click Ignore to hide them from future previews.
                    </Trans>
                </Text>
                <Text size="sm" mb="xs">
                    <strong><Trans>Different Answers</Trans></strong>
                    {' — '}
                    <Trans>
                        Event answers that don't yet match the contact's stored value. Includes
                        first-time fills (attribute was empty) and true conflicts (stored value differs
                        from the event answer). Per row, choose Update to write the event answer or
                        Leave alone to keep the existing value. Click Update to apply your decisions;
                        resolved rows won't reappear on future previews.
                    </Trans>
                </Text>
                <Text size="sm" c="dimmed">
                    <Trans>
                        Each sub-tab runs independently — there's no single "run everything" button.
                    </Trans>
                </Text>
            </Collapse>
        </Paper>
    );
};
