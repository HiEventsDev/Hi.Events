import {useState} from "react";
import {plural, t} from "@lingui/macro";
import {Anchor, Button, Textarea} from "@mantine/core";
import {Modal, ModalFooter} from "../../../../components/common/Modal";
import {IdParam} from "../../../../types.ts";
import {firstApiError} from "../../../../utilites/apiErrors.ts";
import {formatDateWithLocale} from "../../../../utilites/dates.ts";
import {useLookupCrmSuppressions} from "../../mutations/useCrmSuppressionMutations.ts";
import {CrmSuppressionLookupResult, CrmSuppressionReason} from "../../types.ts";
import {marketingStatusLabel} from "../../utilites/labels.ts";
import {suppressionReasonLabel, suppressionSourceLabel} from "../../utilites/suppression.ts";
import classes from "./CheckAddressesModal.module.scss";

const MAX_ADDRESSES = 50;

const parseAddresses = (value: string): string[] => [...new Set(value.split(/[\s,;]+/).map(item => item.trim()).filter(Boolean))];

const deletedSuppressionLabel = (reason: CrmSuppressionReason): string => ({
    UNSUBSCRIBED: t`Deleted contact. They had unsubscribed before they were deleted, so they stay suppressed.`,
    BOUNCED: t`Deleted contact. Their address had bounced before they were deleted, so they stay suppressed.`,
    COMPLAINED: t`Deleted contact. They had marked an email as spam before they were deleted, so they stay suppressed.`,
})[reason];

const ResultLine = ({result, timezone, onOpenContact}: {
    result: CrmSuppressionLookupResult,
    timezone: string,
    onOpenContact: (contactId: number) => void,
}) => {
    const describe = (): string => {
        switch (result.result) {
            case 'invalid':
                return t`Not a valid email address`;
            case 'not_found':
                return t`Not in your audience`;
            case 'deleted':
                return result.deleted_suppression
                    ? deletedSuppressionLabel(result.deleted_suppression)
                    : t`Deleted contact. Not suppressed.`;
            case 'contact': {
                if (result.suppression) {
                    const since = formatDateWithLocale(result.suppression.since, 'shortDate', timezone);

                    return [
                        suppressionReasonLabel(result.suppression.reason),
                        t`Since ${since}`,
                        suppressionSourceLabel(result.suppression),
                    ].join(' · ');
                }
                return result.contact ? marketingStatusLabel(result.contact.email_marketing_status) : '';
            }
        }
    };

    return (
        <li className={classes.result} data-result={result.result} data-suppressed={result.suppression || result.deleted_suppression ? true : undefined}>
            <span className={classes.email}>{result.email}</span>
            <span className={classes.description}>{describe()}</span>
            {result.contact && (
                <Anchor className={classes.open} component="button" type="button" size="sm" onClick={() => onOpenContact(result.contact!.id)}>{t`Open`}</Anchor>
            )}
        </li>
    );
};

export const CheckAddressesModal = ({organizerId, timezone, onOpenContact, onClose}: {
    organizerId: IdParam,
    timezone: string,
    onOpenContact: (contactId: number) => void,
    onClose: () => void,
}) => {
    const lookup = useLookupCrmSuppressions();
    const [value, setValue] = useState('');
    const addresses = parseAddresses(value);
    const isTooMany = addresses.length > MAX_ADDRESSES;
    const results = lookup.data?.data;

    return (
        <Modal opened onClose={onClose} heading={t`Check addresses`} size="lg">
            <Textarea
                label={t`Email addresses`}
                description={t`See whether campaigns can reach these addresses, including people you deleted. Paste up to 50, one per line or separated by commas.`}
                autosize
                minRows={3}
                maxRows={8}
                value={value}
                onChange={event => setValue(event.currentTarget.value)}
                error={isTooMany ? t`Check up to 50 addresses at a time.` : lookup.isError ? firstApiError(lookup.error, t`We couldn't check these addresses. Please try again.`) : undefined}
                data-testid="audience-check-addresses-input"
            />

            {results && (
                <ul className={classes.results} data-testid="audience-check-addresses-results">
                    {results.map(result => (
                        <ResultLine key={result.email} result={result} timezone={timezone} onOpenContact={onOpenContact}/>
                    ))}
                </ul>
            )}

            <ModalFooter>
                <Button variant="default" onClick={onClose}>{t`Close`}</Button>
                <Button
                    onClick={() => lookup.mutate({organizerId, emails: addresses})}
                    disabled={addresses.length === 0 || isTooMany}
                    loading={lookup.isPending}
                    data-testid="audience-check-addresses-submit"
                >
                    {addresses.length > 0 ? plural(addresses.length, {one: 'Check # address', other: 'Check # addresses'}) : t`Check`}
                </Button>
            </ModalFooter>
        </Modal>
    );
};
