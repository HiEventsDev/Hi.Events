import {t, Trans} from "@lingui/macro";
import {Button, Popover, Stack, Text, Title} from "@mantine/core";
import {IconHelpCircle} from "@tabler/icons-react";

export const BackfillHelpPopover = () => {
    return (
        <Popover width={440} position="bottom-end" withArrow shadow="md">
            <Popover.Target>
                <Button
                    size="xs"
                    variant="light"
                    color="blue"
                    leftSection={<IconHelpCircle size={14}/>}
                >
                    {t`Explain Sync`}
                </Button>
            </Popover.Target>
            <Popover.Dropdown>
                <Stack gap="xs">
                    <Title order={5}>{t`About Sync`}</Title>
                    <Text size="sm">
                        <Trans>
                            Your contacts library unifies attendees across events. When you add a new contact
                            attribute (like "Dietary preference") or start using contacts mid-way through your
                            account's history, answers from past events don't retroactively flow in. Sync
                            reconciles that history.
                        </Trans>
                    </Text>
                    <Text size="sm">
                        <Trans>Three things to review and sync, one per sub-tab:</Trans>
                    </Text>
                    <Text size="sm">
                        <strong><Trans>New Contacts</Trans></strong>
                        {' — '}
                        <Trans>
                            Attendees from past orders whose email isn't in your contacts library yet. Select
                            the rows you want and click Add to create contacts for them; click Ignore to hide
                            them from future previews.
                        </Trans>
                    </Text>
                    <Text size="sm">
                        <strong><Trans>New Questions</Trans></strong>
                        {' — '}
                        <Trans>
                            Registration questions used on past events that aren't marked as reusable. Select
                            the rows and click Reuse to create an extended attribute from each question and
                            link them; click Ignore to hide them from future previews.
                        </Trans>
                    </Text>
                    <Text size="sm">
                        <strong><Trans>Different Answers</Trans></strong>
                        {' — '}
                        <Trans>
                            Event answers that don't yet match the contact's stored value. Includes
                            first-time fills (attribute was empty) and true conflicts (stored value differs
                            from the event answer). Select the rows you want and click Update to write the
                            event answers onto the contacts; click Ignore to keep existing values. Resolved
                            rows won't reappear unless you toggle Show processed.
                        </Trans>
                    </Text>
                    <Text size="sm" c="dimmed">
                        <Trans>
                            Each sub-tab runs independently — there's no single "run everything" button.
                        </Trans>
                    </Text>
                </Stack>
            </Popover.Dropdown>
        </Popover>
    );
};
