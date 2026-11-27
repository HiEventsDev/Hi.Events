import {useState} from "react";
import {t} from "@lingui/macro";
import {Avatar, Tooltip} from "@mantine/core";
import {parsePhoneNumberFromString} from "libphonenumber-js";
import {IconArrowMerge, IconMailOff, IconMessageOff, IconPencil, IconTrash} from "@tabler/icons-react";
import {EntityAction, EntityActionBar} from "../../../../components/common/EntityActions";
import {
    SideDrawer,
    SideDrawerHeading,
    SideDrawerSection,
    SideDrawerStats,
} from "../../../../components/common/SideDrawer";
import {useAccess} from "../../../../hooks/useAccess.ts";
import {IdParam} from "../../../../types.ts";
import {apiErrorStatus, firstApiError} from "../../../../utilites/apiErrors.ts";
import {confirmationDialog} from "../../../../utilites/confirmationDialog.tsx";
import {formatCurrency} from "../../../../utilites/currency.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {useCrmAccess} from "../../hooks/useCrmAccess.ts";
import {useTextMessagesVisible} from "../../hooks/useTextMessagesVisible.ts";
import {useDeleteCrmContact} from "../../mutations/useDeleteCrmContact.ts";
import {useUnsubscribeCrmContact} from "../../mutations/useUnsubscribeCrmContact.ts";
import {useGetCrmContact} from "../../queries/useGetCrmContact.ts";
import {useGetCrmContactActivities} from "../../queries/useGetCrmContactActivities.ts";
import {CrmContactEmail} from "../../types.ts";
import {deleteContactsConsequence, smsOptOutConsequence, unsubscribeContactConsequence} from "../../utilites/contactConfirmations.ts";
import {contactAvatarColor, contactDisplayName, contactInitials, formatCount} from "../../utilites/format.ts";
import {marketingStatusDescription, smsMarketingStatusDescription, sourceLabel} from "../../utilites/labels.ts";
import {SMS_COUNTRIES, smsOptsOutByReply} from "../../utilites/smsCountries.ts";
import {formatPhoneNumber} from "../../../../utilites/phoneNumber.ts";
import {MarketingStatusBadge} from "../MarketingStatusBadge";
import {ActivityTimeline} from "./ActivityTimeline.tsx";
import {ConsentHistory} from "./ConsentHistory.tsx";
import {EditNameModal} from "./EditNameModal.tsx";
import {EmailAddresses} from "./EmailAddresses.tsx";
import {MergeModal} from "./MergeModal.tsx";
import {personName} from "./names.ts";
import {Notes} from "./Notes.tsx";
import {SuppressionNotice} from "./SuppressionNotice.tsx";
import {Tags} from "./Tags.tsx";
import classes from "./ContactDrawer.module.scss";

interface ContactDrawerProps {
    organizerId: IdParam;
    contactId: IdParam;
    timezone: string;
    onClose: () => void;
}

const GUEST_PREVIEW = 8;

const isLastChoiceOptOut = (emails: CrmContactEmail[]): boolean => {
    const latest = emails.reduce<CrmContactEmail | null>(
        (newest, email) => email.preference_at && email.preference_at > (newest?.preference_at ?? '') ? email : newest,
        null,
    );

    return latest?.preference_action === 'OPT_OUT';
};

export const ContactDrawer = ({organizerId, contactId, timezone, onClose}: ContactDrawerProps) => {
    const {can} = useAccess();
    const {canChange, canRemove} = useCrmAccess();
    const showTextMessages = useTextMessagesVisible();
    const contactQuery = useGetCrmContact(organizerId, contactId);
    const activities = useGetCrmContactActivities(organizerId, contactId);
    const deleteContact = useDeleteCrmContact();
    const unsubscribe = useUnsubscribeCrmContact();
    const [modal, setModal] = useState<'edit' | 'merge' | null>(null);
    const [showAllGuests, setShowAllGuests] = useState(false);
    const contact = contactQuery.data;

    if (contactQuery.isError) {
        const isNotFound = apiErrorStatus(contactQuery.error) === 404;

        return (
            <SideDrawer
                opened
                onClose={onClose}
                header={<SideDrawerHeading title={isNotFound ? t`Contact not found` : t`Something went wrong`}/>}
            >
                <p className={`${classes.empty} ${classes.errorMessage}`}>
                    {isNotFound
                        ? t`This contact no longer exists. They may have been merged or deleted.`
                        : t`We couldn't load this contact. Please refresh the page.`}
                </p>
            </SideDrawer>
        );
    }

    if (!contact) {
        return <SideDrawer opened onClose={onClose} loading/>;
    }

    const handleDelete = () => confirmationDialog(
        t`Delete this contact?`,
        () => deleteContact.mutate({organizerId, contactId: contact.id}, {
            onSuccess: () => {
                showSuccess(t`Contact deleted`);
                onClose();
            },
            onError: error => showError(firstApiError(error, t`We couldn't delete this contact. Please try again.`)),
        }),
        {confirm: t`Delete contact`, destructive: true, description: deleteContactsConsequence()},
    );

    const emails = contact.emails ?? [];
    const emailStatus = contact.email_marketing_status;
    const canUnsubscribe = emailStatus === 'SUBSCRIBED'
        || emailStatus === 'NOT_SUBSCRIBED'
        || (emailStatus === 'BOUNCED' && !isLastChoiceOptOut(emails));
    const phoneCountry = contact.phone ? parsePhoneNumberFromString(contact.phone)?.country : undefined;
    const canOptInByReply = SMS_COUNTRIES.some(country => country === phoneCountry && smsOptsOutByReply(country));

    const handleUnsubscribe = () => confirmationDialog(
        t`Unsubscribe this contact from emails?`,
        () => unsubscribe.mutate({organizerId, contactId: contact.id}, {
            onSuccess: () => showSuccess(emailStatus === 'BOUNCED' ? t`Unsubscribe recorded` : t`Unsubscribed`),
            onError: error => showError(firstApiError(error, t`We couldn't unsubscribe this contact. Please try again.`)),
        }),
        {confirm: t`Unsubscribe`, destructive: true, description: unsubscribeContactConsequence(emailStatus)},
    );

    const handleOptOutOfTexts = () => confirmationDialog(
        t`Opt this contact out of text messages?`,
        () => unsubscribe.mutate({organizerId, contactId: contact.id, channel: 'SMS'}, {
            onSuccess: () => showSuccess(t`Opted out of text messages`),
            onError: error => showError(firstApiError(error, t`We couldn't opt this contact out. Please try again.`)),
        }),
        {confirm: t`Opt out`, destructive: true, description: smsOptOutConsequence(canOptInByReply)},
    );

    const actions: EntityAction[] = canRemove ? [
        ...(canChange ? [
            {key: 'edit', label: t`Edit name`, icon: <IconPencil size={16}/>, onClick: () => setModal('edit'), group: 'secondary' as const},
            {key: 'merge', label: t`Merge with another contact`, icon: <IconArrowMerge size={16}/>, onClick: () => setModal('merge'), group: 'secondary' as const},
        ] : []),
        ...(canUnsubscribe ? [{
            key: 'unsubscribe',
            label: t`Unsubscribe from emails`,
            icon: <IconMailOff size={16}/>,
            onClick: handleUnsubscribe,
            group: 'secondary' as const,
        }] : []),
        ...(showTextMessages && contact.sms_marketing_status === 'SUBSCRIBED' ? [{
            key: 'unsubscribe-sms',
            label: t`Opt out of text messages`,
            icon: <IconMessageOff size={16}/>,
            onClick: handleOptOutOfTexts,
            group: 'secondary' as const,
        }] : []),
        {key: 'delete', label: t`Delete contact`, icon: <IconTrash size={16}/>, onClick: handleDelete, group: 'danger', color: 'red'},
    ] : [];

    const spend = (can('VIEW_FINANCIALS') ? (contact.spend ?? []) : []).filter(row => row.net !== 0 || row.total_refunded > 0);
    const guests = (contact.names ?? []).filter(name => name.role === 'GUEST');
    const aliases = (contact.names ?? []).filter(name => name.role === 'SELF'
        && personName(name.first_name, name.last_name).toLowerCase() !== contact.full_name.toLowerCase());

    return (
        <SideDrawer
            opened
            onClose={onClose}
            closeOnEscape={modal === null}
            header={(
                <SideDrawerHeading
                    media={<Avatar size={42} radius="xl" color={contactAvatarColor(contact)}>{contactInitials(contact)}</Avatar>}
                    title={<div className={classes.title}>{contactDisplayName(contact)}</div>}
                    subtitle={contact.full_name ? contact.email : sourceLabel(contact.source)}
                />
            )}
            actions={actions.length > 0 ? <EntityActionBar actions={actions}/> : undefined}
        >
            <SideDrawerStats stats={[
                {label: t`Events`, value: formatCount(contact.events_count)},
                {label: t`Tickets`, value: formatCount(contact.tickets_count)},
                {label: t`Check-ins`, value: formatCount(contact.attended_count)},
                {
                    label: t`No-shows`,
                    value: (
                        <Tooltip
                            label={t`Event dates that have ended and had at least one check-in, where this person held a ticket but didn't check in.`}
                            multiline
                            w={260}
                        >
                            <span className={classes.explained} tabIndex={0}>{formatCount(contact.no_show_count ?? 0)}</span>
                        </Tooltip>
                    ),
                },
            ]}/>

            <SideDrawerSection title={t`Email status`} surface>
                <MarketingStatusBadge status={contact.email_marketing_status}/>
                <p className={classes.statusDescription}>{marketingStatusDescription(contact.email_marketing_status)}</p>
                <SuppressionNotice organizerId={organizerId} contact={contact} timezone={timezone} canChange={canChange}/>
            </SideDrawerSection>

            {!showTextMessages && contact.phone && (
                <SideDrawerSection title={t`Mobile number`} surface>
                    <p className={classes.empty}>{formatPhoneNumber(contact.phone)}</p>
                </SideDrawerSection>
            )}

            {showTextMessages && (contact.phone || contact.sms_marketing_status !== 'NOT_SUBSCRIBED') && (
                <SideDrawerSection title={t`Text message status`} surface>
                    <MarketingStatusBadge status={contact.sms_marketing_status}/>
                    {contact.phone && <p className={classes.statusDescription}>{formatPhoneNumber(contact.phone)}</p>}
                    <p className={classes.statusDescription}>{smsMarketingStatusDescription(contact.sms_marketing_status)}</p>
                </SideDrawerSection>
            )}

            <Tags organizerId={organizerId} contact={contact} canEdit={canChange}/>

            {spend.length > 0 && (
                <SideDrawerSection title={t`Spend`} surface>
                    <div className={classes.list}>
                        {spend.map(row => (
                            <div key={row.currency} className={classes.listItem}>
                                <span className={classes.listMain}>{formatCurrency(row.net, row.currency)}</span>
                                {row.total_refunded > 0 && (
                                    <span className={classes.listMeta}>
                                        {t`${formatCurrency(row.total_refunded, row.currency)} refunded`}
                                    </span>
                                )}
                            </div>
                        ))}
                    </div>
                </SideDrawerSection>
            )}

            {emails.length > 1 && <EmailAddresses organizerId={organizerId} contact={contact} canEdit={canChange}/>}

            {aliases.length > 0 && (
                <SideDrawerSection title={t`Other names used`}>
                    <p className={classes.empty}>
                        {aliases.map(name => personName(name.first_name, name.last_name)).join(', ')}
                    </p>
                </SideDrawerSection>
            )}

            {guests.length > 0 && (
                <SideDrawerSection title={t`Also buys tickets for`} count={guests.length}>
                    <div className={classes.guests}>
                        {(showAllGuests ? guests : guests.slice(0, GUEST_PREVIEW)).map(name => (
                            <span key={`guest-${name.first_name}-${name.last_name}`} className={classes.guest}>
                                {personName(name.first_name, name.last_name)}
                                {name.seen_count > 1 && <small>×{name.seen_count}</small>}
                            </span>
                        ))}
                        {guests.length > GUEST_PREVIEW && (
                            <button type="button" className={classes.guestToggle} onClick={() => setShowAllGuests(value => !value)}>
                                {showAllGuests ? t`Show fewer` : t`+${guests.length - GUEST_PREVIEW} more`}
                            </button>
                        )}
                    </div>
                </SideDrawerSection>
            )}

            <ActivityTimeline activities={activities} timezone={timezone}/>

            <ConsentHistory organizerId={organizerId} contactId={contact.id} timezone={timezone} showsEmailAddress={emails.length > 1}/>

            <Notes organizerId={organizerId} contactId={contact.id} canAdd={canChange} canDelete={canRemove}/>

            {modal === 'edit' && <EditNameModal organizerId={organizerId} contact={contact} onClose={() => setModal(null)}/>}
            {modal === 'merge' && <MergeModal organizerId={organizerId} contact={contact} onClose={() => setModal(null)}/>}
        </SideDrawer>
    );
};
