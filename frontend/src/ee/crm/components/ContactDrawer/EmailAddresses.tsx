import {t} from "@lingui/macro";
import {Button, Tooltip} from "@mantine/core";
import {SideDrawerSection} from "../../../../components/common/SideDrawer";
import {IdParam} from "../../../../types.ts";
import {firstApiError} from "../../../../utilites/apiErrors.ts";
import {confirmationDialog} from "../../../../utilites/confirmationDialog.tsx";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {useSetPrimaryCrmContactEmail} from "../../mutations/useSetPrimaryCrmContactEmail.ts";
import {useSplitCrmContactEmail} from "../../mutations/useSplitCrmContactEmail.ts";
import {CrmContact} from "../../types.ts";
import {consentActionLabel} from "../../utilites/labels.ts";
import classes from "./ContactDrawer.module.scss";

type ContactEmail = NonNullable<CrmContact['emails']>[number];

const consentMeta = (email: ContactEmail): { label: string, hint: string } | null => {
    switch (email.consent_action) {
        case 'OPT_IN':
            return {
                label: consentActionLabel('OPT_IN'),
                hint: email.is_primary
                    ? t`This opt-in counts because this is the primary address.`
                    : t`This opt-in only counts if this becomes the primary address.`,
            };
        case 'OPT_OUT':
            return {label: consentActionLabel('OPT_OUT'), hint: t`An opt-out from any address stops campaigns to this person.`};
        case 'COMPLAINED':
            return {label: consentActionLabel('COMPLAINED'), hint: t`A spam complaint from any address stops campaigns to this person for good.`};
        case 'BOUNCED':
            return {
                label: consentActionLabel('BOUNCED'),
                hint: email.is_primary
                    ? t`This address rejected an email, so campaigns skip this person.`
                    : t`This address rejected an email. Campaigns would skip this person if it became the primary address.`,
            };
        default:
            return null;
    }
};

const EmailConsent = ({email}: { email: ContactEmail }) => {
    const meta = consentMeta(email);

    if (!meta) {
        return null;
    }

    return (
        <Tooltip label={meta.hint} multiline w={240}>
            <span className={classes.listMeta} tabIndex={0}>{meta.label}</span>
        </Tooltip>
    );
};

export const EmailAddresses = ({organizerId, contact, canEdit}: {
    organizerId: IdParam,
    contact: CrmContact,
    canEdit: boolean,
}) => {
    const splitEmail = useSplitCrmContactEmail();
    const setPrimary = useSetPrimaryCrmContactEmail();
    const emails = contact.emails ?? [];

    const makePrimary = (email: ContactEmail) => setPrimary.mutate({organizerId, contactId: contact.id, contactEmailId: email.id}, {
        onSuccess: () => showSuccess(t`Primary email updated`),
        onError: error => showError(firstApiError(error, t`We couldn't change the primary email. Please try again.`)),
    });

    const handleMakePrimary = (email: ContactEmail) => {
        const losesConsent = contact.email_marketing_status === 'SUBSCRIBED' && email.consent_action !== 'OPT_IN';
        const gainsConsent = contact.email_marketing_status === 'NOT_SUBSCRIBED' && email.consent_action === 'OPT_IN';

        if (!losesConsent && !gainsConsent) {
            makePrimary(email);
            return;
        }
        const address = email.email;
        confirmationDialog(
            t`Make ${address} the primary address?`,
            () => makePrimary(email),
            {
                confirm: t`Make primary`,
                description: losesConsent
                    ? t`This address never opted in. Marketing consent follows the primary address, so this person will stop receiving campaigns.`
                    : t`This address opted in. Marketing consent follows the primary address, so this person will start receiving campaigns.`,
            },
        );
    };

    const handleSplit = (email: ContactEmail) => {
        const address = email.email;
        confirmationDialog(
            t`Separate ${address} into its own contact?`,
            () => splitEmail.mutate({organizerId, contactId: contact.id, contactEmailId: email.id}, {
                onSuccess: () => showSuccess(t`Email separated into its own contact`),
                onError: error => showError(firstApiError(error, t`We couldn't separate this email. Please try again.`)),
            }),
            {
                confirm: t`Separate email`,
                description: t`Orders placed with this address move to the new contact. You can merge them again later.`,
            },
        );
    };

    return (
        <SideDrawerSection title={t`Email addresses`} surface>
            <div className={classes.list}>
                {emails.map(email => (
                    <div key={email.id} className={classes.emailRow}>
                        <span className={classes.emailAddress}>{email.email}</span>
                        <span className={classes.emailMeta}>
                            {email.is_primary && <span className={classes.pill}>{t`Primary`}</span>}
                            <EmailConsent email={email}/>
                            {!email.is_primary && canEdit && (
                                <span className={classes.emailActions}>
                                    <Tooltip label={t`Campaigns go to the primary address, and marketing consent follows it.`} multiline w={240}>
                                        <Button
                                            variant="subtle"
                                            color="gray"
                                            size="compact-xs"
                                            loading={setPrimary.isPending && setPrimary.variables?.contactEmailId === email.id}
                                            onClick={() => handleMakePrimary(email)}
                                        >
                                            {t`Make primary`}
                                        </Button>
                                    </Tooltip>
                                    <Tooltip label={t`Separate into its own contact`}>
                                        <Button
                                            variant="subtle"
                                            color="gray"
                                            size="compact-xs"
                                            loading={splitEmail.isPending && splitEmail.variables?.contactEmailId === email.id}
                                            onClick={() => handleSplit(email)}
                                        >
                                            {t`Separate`}
                                        </Button>
                                    </Tooltip>
                                </span>
                            )}
                        </span>
                    </div>
                ))}
            </div>
            <p className={classes.consentNote}>
                {t`Campaigns go to the primary address, and only an opt-in from it counts. An unsubscribe or spam complaint from any of these addresses stops campaigns.`}
            </p>
        </SideDrawerSection>
    );
};
