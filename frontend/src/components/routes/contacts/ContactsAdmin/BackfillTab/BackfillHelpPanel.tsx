import { t, Trans } from "@lingui/macro";
import { Button, Popover, Stack, Text, Title } from "@mantine/core";
import { IconHelpCircle } from "@tabler/icons-react";

export const BackfillHelpPopover = () => {
  return (
    <Popover width={440} position="bottom-end" withArrow shadow="md">
      <Popover.Target>
        <Button
          size="xs"
          variant="light"
          color="blue"
          leftSection={<IconHelpCircle size={14} />}
        >
          {t`Explain`}
        </Button>
      </Popover.Target>
      <Popover.Dropdown>
        <Stack gap="xs">
          <Title order={5}>{t`About Contacts`}</Title>
          <Text size="sm">
            <Trans>
              <strong>Contacts</strong> are <i>attendees</i> remembered across events.
              <p>
                Whenever a new attendee registers for the first time, a new contact record is created for them, saving their name and email address, along with answers to any registration questions made up to that point.
              </p>
              <p>
                Later (whevener that email is used again in future), the contact's name is copied into the order form automatically and their previous answers will be remebered too.
                For example, suppose <i>Extra Large (XL)</i> was previously saved as an answer to <i>"What is your t-shirt size?"</i>
                The next time that contact registers for an event, the answer <i>Extra Large (XL)</i> will be copied into the order form automatically, (provided that <i>t-shirt</i> was saved as a <i>reusable</i> question.)
              </p>
            </Trans>
          </Text>
          <Title order={5}>{t`About Sync`}</Title>
          <Text size="sm">
            <Trans>
              <i>
                But wait... What happens if an attendee provides different answers to the same question on different events? What if you need a registration question on one event but don't care about re-using it on another? &nbsp;
              </i>
              &emsp;This is where the <strong>Sync</strong> tab comes in.
            </Trans>
          </Text>
          <Text size="sm">
            <strong><Trans>New Contacts</Trans></strong>
            {' — '}
            <Trans>
              Attendees with email not found in the contacts library yet. Select rows and click <i>Add</i> to create contacts for them; click <i>Ignore</i> to prevent them from re-appearing on this tab in future.
            </Trans>
          </Text>
          <Text size="sm">
            <strong><Trans>New Questions</Trans></strong>
            {' — '}
            <Trans>
              Registration questions used on past events not marked as reusable. Select and click <i>Reuse</i> to create a new extended attribute (relevant to all contacts); click <i>Ignore</i> to keep it as an answer relevant only on that event.
            </Trans>
          </Text>
          <Text size="sm">
            <strong><Trans>Different Answers</Trans></strong>
            {' — '}
            <Trans>
              Whenver an attendee's answer to a reusable question differs from the value stored on their contact, that discrepancy will appear here. Select <i>Update</i> to change the contact default; click <i>Ignore</i> to keep it the same.
            </Trans>
          </Text>
        </Stack>
      </Popover.Dropdown>
    </Popover>
  );
};
