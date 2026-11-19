import {plural, t} from "@lingui/macro";
import {CrmAutomation} from "../../../types.ts";
import {formatCount} from "../../../utilites/format.ts";

export const automationFailureReason = (
    code: string,
    params: CrmAutomation['last_failure_reason_params'],
): string => {
    switch (code) {
        case 'ACCOUNT_NOT_VERIFIED':
            return t`Your account's email address hasn't been confirmed yet. Check your inbox for the confirmation email, then the next one will send.`;
        case 'ACCOUNT_NOT_APPROVED': {
            const email = String(params?.email ?? '');
            return email
                ? t`To protect people from spam, we enable campaigns for each account by hand, and yours isn't enabled yet. Contact us at ${email}.`
                : t`To protect people from spam, we enable campaigns for each account by hand, and yours isn't enabled yet. Contact us to get it enabled.`;
        }
        case 'TOO_MANY_RECIPIENTS': {
            const count = Number(params?.count ?? 0);
            const maxRecipients = Number(params?.max ?? 0);
            const max = formatCount(maxRecipients);
            return count > 0 && maxRecipients > 0
                ? plural(count, {
                    one: `It would have gone to # person, which is more than your account can send to at once (${max}). Choose a smaller segment for this automation.`,
                    other: `It would have gone to # people, which is more than your account can send to at once (${max}). Choose a smaller segment for this automation.`,
                })
                : t`It would have gone to more people than your account can send to at once. Choose a smaller segment for this automation.`;
        }
        case 'LINKS_NOT_ALLOWED':
            return t`Your account can't send links yet, and this automation's email contains one. Edit the email to remove the links.`;
        case 'EMPTY_CONTENT':
            return t`This automation's email has no subject or no message. Edit it to add them.`;
        case 'INVALID_PLACEHOLDER':
            return t`This automation's email uses a placeholder that isn't available. Edit the email and insert placeholders from the menu.`;
        case 'SEGMENT_DELETED':
            return t`The segment this automation sends to was deleted. Edit the automation and choose who gets it.`;
        case 'EVENT_AWAITING_REVIEW': {
            const eventTitle = String(params?.event_title ?? '');
            return eventTitle
                ? t`"${eventTitle}" is still being checked before it can be announced. The announcement goes out once the check passes.`
                : t`An event is still being checked before it can be announced. The announcement goes out once the check passes.`;
        }
        case 'REVIEW_EXPIRED': {
            const hours = Number(params?.hours ?? 0);
            return hours > 0
                ? plural(hours, {
                    one: `The last email wasn't sent: it waited more than # hour for review. The next one is sent for review again.`,
                    other: `The last email wasn't sent: it waited more than # hours for review. The next one is sent for review again.`,
                })
                : t`The last email wasn't sent: it waited too long for review. The next one is sent for review again.`;
        }
        default:
            return t`The last email couldn't be sent. The automation tries again on its next run. If this stays here, check its email and who it sends to.`;
    }
};
