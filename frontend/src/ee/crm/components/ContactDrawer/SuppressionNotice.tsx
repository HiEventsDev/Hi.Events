import {t} from "@lingui/macro";
import {Button, Tooltip} from "@mantine/core";
import {IconRefresh} from "@tabler/icons-react";
import {IdParam} from "../../../../types.ts";
import {firstApiError} from "../../../../utilites/apiErrors.ts";
import {confirmationDialog} from "../../../../utilites/confirmationDialog.tsx";
import {formatDateWithLocale} from "../../../../utilites/dates.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {useClearCrmBounce} from "../../mutations/useCrmSuppressionMutations.ts";
import {CrmContact, CrmContactEmail} from "../../types.ts";
import {suppressionSourceLabel} from "../../utilites/suppression.ts";
import classes from "./SuppressionNotice.module.scss";

export const SuppressionNotice = ({organizerId, contact, timezone, canChange}: {
    organizerId: IdParam,
    contact: CrmContact,
    timezone: string,
    canChange: boolean,
}) => {
    const clearBounce = useClearCrmBounce();
    const detail = contact.suppression;

    if (!detail) {
        return null;
    }

    const since = formatDateWithLocale(detail.since, 'shortDate', timezone);
    const source = suppressionSourceLabel(detail);
    const emails = contact.emails ?? [];
    const primary = emails.find(email => email.is_primary);
    const workingAlternative = detail.reason === 'BOUNCED'
        ? emails.find(email => !email.is_primary && email.consent_action !== 'BOUNCED' && email.consent_action !== 'COMPLAINED')
        : undefined;
    const canReactivate = detail.reason === 'BOUNCED' && canChange && primary?.consent_action === 'BOUNCED';
    const availableAt = detail.reactivation_available_at;
    const availableOn = availableAt ? formatDateWithLocale(availableAt, 'shortDate', timezone) : '';
    const alternativeAddress = workingAlternative?.email ?? '';

    const reactivate = (email: CrmContactEmail) => {
        const address = email.email;
        const preference = email.preference_action;
        const preferenceAt = email.preference_at ? formatDateWithLocale(email.preference_at, 'shortDate', timezone) : '';

        confirmationDialog(
            t`Reactivate ${address}?`,
            () => clearBounce.mutate({organizerId, contactId: contact.id, contactEmailId: email.id}, {
                onSuccess: () => showSuccess(t`Address reactivated`),
                onError: error => showError(firstApiError(error, t`We couldn't reactivate this address. Please try again.`)),
            }),
            {
                confirm: t`Reactivate`,
                description: [
                    t`Only do this if the address works again. This doesn't subscribe them: they'll only get campaigns if they had opted in before the bounce.`,
                    preference === 'OPT_IN'
                        ? t`Before the bounce: opted in on ${preferenceAt}.`
                        : preference === 'OPT_OUT'
                            ? t`Before the bounce: opted out on ${preferenceAt}.`
                            : t`Before the bounce: never opted in.`,
                ].join(' '),
            },
        );
    };

    return (
        <div className={classes.notice}>
            <p className={classes.source}>
                {t`Since ${since}`} · {source}
            </p>
            {workingAlternative && (
                <p className={classes.hint}>
                    {t`${alternativeAddress} hasn't bounced. Make it the primary address under "Email addresses" to email them there.`}
                </p>
            )}
            {canReactivate && primary && (
                <Tooltip
                    label={availableAt ? t`The email provider refused this address. You can reactivate it from ${availableOn}.` : ''}
                    disabled={!availableAt}
                    multiline
                    w={260}
                >
                    <span className={classes.action}>
                        <Button
                            variant="default"
                            size="compact-sm"
                            leftSection={<IconRefresh size={14}/>}
                            disabled={!!availableAt}
                            loading={clearBounce.isPending}
                            onClick={() => reactivate(primary)}
                            data-testid="contact-reactivate-bounce"
                        >
                            {t`Reactivate address`}
                        </Button>
                    </span>
                </Tooltip>
            )}
        </div>
    );
};
