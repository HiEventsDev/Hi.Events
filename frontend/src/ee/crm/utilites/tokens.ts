/* eslint-disable lingui/no-unlocalized-strings */
import {t} from "@lingui/macro";
import {EmailTemplateToken} from "../../../types.ts";
import {CrmAutomationTrigger} from "../types.ts";

const personalisationTokens = (): EmailTemplateToken[] => [
    {
        token: '{{ first_name | default: "there" }}',
        description: t`The recipient's first name. Change "there" to the word used when we don't know their name.`,
        example: t`e.g. Ada`,
    },
    {
        token: '{{ last_name }}',
        description: t`The recipient's last name. Left blank when we don't know it.`,
        example: t`e.g. Lovelace`,
    },
];

const lastEventTokens = (): EmailTemplateToken[] => [
    {
        token: '{{ last_event_title | default: "our last event" }}',
        description: t`The last event they checked in to. Change the fallback to the words used when they haven't been to one.`,
        example: t`e.g. Summer Jazz Night`,
    },
    {
        token: '{{ last_event_date }}',
        description: t`The date of the last event they checked in to. Left blank when they haven't been to one.`,
        example: t`e.g. June 5, 2027`,
    },
];

const organizerToken = (): EmailTemplateToken => ({token: '{{ organizer_name }}', description: t`Your organizer name`, example: t`e.g. Blue Note Events`});

const eventTokens = (): EmailTemplateToken[] => [
    {token: '{{ event_title }}', description: t`The event's name`, example: t`e.g. Summer Jazz Night`},
    {token: '{{ event_date }}', description: t`The date of the event, in the recipient's language`, example: t`e.g. June 5, 2027`},
    {token: '{{ event_url }}', description: t`A link to the event page. Includes the early access code when the campaign offers early access.`, example: t`e.g. https://tickets.example.com/event/12/summer-jazz`},
];

export const campaignTokens = (hasEvent: boolean): EmailTemplateToken[] => [
    ...personalisationTokens(),
    ...lastEventTokens(),
    organizerToken(),
    ...(hasEvent ? eventTokens() : []),
];

const EVENT_TRIGGERS: CrmAutomationTrigger[] = ['EVENT_PUBLISHED', 'AFTER_EVENT', 'BEFORE_EVENT', 'ABANDONED_CHECKOUT'];

export const automationTokens = (trigger: CrmAutomationTrigger): EmailTemplateToken[] => campaignTokens(EVENT_TRIGGERS.includes(trigger));

export const insertAtCursor = (value: string, token: string, input: HTMLInputElement | HTMLTextAreaElement | null): string => {
    const start = input?.selectionStart ?? value.length;
    const end = input?.selectionEnd ?? value.length;

    return value.slice(0, start) + token + value.slice(end);
};
