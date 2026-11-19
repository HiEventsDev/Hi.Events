import {useState} from "react";
import {useSearchParams} from "react-router";
import {t} from "@lingui/macro";
import {Avatar, Button, Loader, Radio, Stack, TextInput} from "@mantine/core";
import {useDebouncedValue} from "@mantine/hooks";
import {Modal, ModalFooter} from "../../../../components/common/Modal";
import {IdParam} from "../../../../types.ts";
import {firstApiError} from "../../../../utilites/apiErrors.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {useMergeCrmContacts} from "../../mutations/useMergeCrmContacts.ts";
import {useGetCrmContacts} from "../../queries/useGetCrmContacts.ts";
import {CrmContact} from "../../types.ts";
import {contactAvatarColor, contactDisplayName, contactInitials} from "../../utilites/format.ts";
import classes from "./ContactDrawer.module.scss";
import {Notice} from "../Notice";

export const MergeModal = ({organizerId, contact, onClose}: { organizerId: IdParam, contact: CrmContact, onClose: () => void }) => {
    const [search, setSearch] = useState('');
    const [query] = useDebouncedValue(search, 250);
    const [selected, setSelected] = useState<CrmContact | null>(null);
    const [keepOther, setKeepOther] = useState(false);
    const [, setSearchParams] = useSearchParams();
    const results = useGetCrmContacts(organizerId, {pageNumber: 1, perPage: 8, query: query.trim()}, undefined, query.trim().length >= 2);
    const mutation = useMergeCrmContacts();
    const candidates = (results.data?.data ?? []).filter(candidate => candidate.id !== contact.id);

    const kept = keepOther && selected ? selected : contact;
    const merged = keepOther ? contact : selected;
    const consentOutcome = (() => {
        if (!merged) {
            return null;
        }
        const keptEmail = kept.email;
        if (kept.email_marketing_status === 'COMPLAINED' || merged.email_marketing_status === 'COMPLAINED') {
            return t`One of these addresses marked a campaign as spam, so the merged contact won't receive campaigns.`;
        }
        if (kept.email_marketing_status !== 'SUBSCRIBED' && merged.email_marketing_status === 'SUBSCRIBED') {
            return t`Only ${keptEmail} decides marketing consent, and it isn't subscribed, so the merged contact won't receive campaigns. Keep the other contact to keep them subscribed.`;
        }
        if (kept.email_marketing_status === 'SUBSCRIBED' && merged.email_marketing_status === 'UNSUBSCRIBED') {
            return t`The other address unsubscribed. If that happened after ${keptEmail} opted in, the merged contact won't receive campaigns.`;
        }
        return null;
    })();

    const handleMerge = () => merged && mutation.mutate({
        organizerId,
        contactId: kept.id,
        sourceContactId: merged.id,
    }, {
        onSuccess: () => {
            showSuccess(t`Contacts merged`);
            if (kept.id !== contact.id) {
                setSearchParams(previous => {
                    const next = new URLSearchParams(previous);
                    next.set('contact', String(kept.id));
                    return next;
                }, {replace: true, flushSync: true});
            }
            onClose();
        },
        onError: error => showError(firstApiError(error, t`We couldn't merge these contacts. Please try again.`)),
    });

    return (
        <Modal opened onClose={onClose} heading={t`Merge with another contact`} size="md">
            <p className={classes.empty}>
                {t`Use this when one person has booked with two email addresses. Orders, tags and notes end up on the contact you keep, and you can separate an address again later.`}
            </p>
            <TextInput
                mt="md"
                label={t`Find the other contact`}
                placeholder={t`Search by name or email`}
                value={search}
                onChange={event => {
                    setSearch(event.currentTarget.value);
                    setSelected(null);
                    setKeepOther(false);
                }}
                data-autofocus
                data-testid="audience-merge-search"
            />
            {query.trim().length >= 2 && (
                <div className={classes.mergeResults} role="group" aria-label={t`Matching contacts`}>
                    {results.isFetching && candidates.length === 0 && (
                        <div className={classes.mergeEmpty}><Loader size="xs" aria-label={t`Searching…`}/></div>
                    )}
                    {results.isError && !results.isFetching && (
                        <p className={`${classes.empty} ${classes.mergeEmpty}`}>{t`We couldn't search your contacts. Please try again.`}</p>
                    )}
                    {results.isSuccess && candidates.length === 0 && !results.isFetching && (
                        <p className={`${classes.empty} ${classes.mergeEmpty}`}>{t`Nobody matches that search.`}</p>
                    )}
                    {candidates.map(candidate => (
                        <button
                            key={candidate.id}
                            type="button"
                            className={classes.mergeOption}
                            data-selected={selected?.id === candidate.id || undefined}
                            aria-pressed={selected?.id === candidate.id}
                            onClick={() => setSelected(candidate)}
                        >
                            <Avatar size={28} radius="xl" color={contactAvatarColor(candidate)}>{contactInitials(candidate)}</Avatar>
                            <span className={classes.mergeText}>
                                <span>{contactDisplayName(candidate)}</span>
                                <small>{candidate.email}</small>
                            </span>
                        </button>
                    ))}
                </div>
            )}
            {selected && (
                <Radio.Group
                    mt="md"
                    label={t`Which contact do you want to keep?`}
                    description={t`Its primary address decides marketing consent.`}
                    value={keepOther ? 'other' : 'this'}
                    onChange={value => setKeepOther(value === 'other')}
                >
                    <Stack gap={6} mt={6}>
                        <Radio value="this" label={contact.email}/>
                        <Radio value="other" label={selected.email}/>
                    </Stack>
                </Radio.Group>
            )}
            {consentOutcome && <Notice style={{marginTop: 16}}>{consentOutcome}</Notice>}
            <ModalFooter>
                <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                <Button
                    disabled={!selected}
                    loading={mutation.isPending}
                    onClick={handleMerge}
                    data-testid="audience-merge-confirm"
                >
                    {t`Merge contacts`}
                </Button>
            </ModalFooter>
        </Modal>
    );
};
