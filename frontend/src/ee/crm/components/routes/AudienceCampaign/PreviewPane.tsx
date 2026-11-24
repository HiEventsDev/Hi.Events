import {useState} from "react";
import {t} from "@lingui/macro";
import {Select} from "@mantine/core";
import {useDebouncedValue} from "@mantine/hooks";
import {BrandingSwitch} from "../../../../../components/common/BrandingUpsell";
import {EmailPreview} from "../../../../../components/common/EmailPreview";
import {useBrandingSubscription} from "../../../../../hooks/useBrandingSubscription.ts";
import {IdParam} from "../../../../../types.ts";
import {useGetCrmContacts} from "../../../queries/useGetCrmContacts.ts";
import {contactDisplayName} from "../../../utilites/format.ts";
import classes from "./Campaign.module.scss";

const YOU = 'you';

interface PreviewPaneProps {
    organizerId: IdParam;
    isEditable: boolean;
    isSent: boolean;
    isHidden: boolean;
    previewContactId: number | null;
    onPreviewContactChange: (contactId: number | null) => void;
    subject: string;
    preheader: string | null;
    fromName: string;
    html: string | undefined;
    isError: boolean;
}

const PreviewAsContact = ({organizerId, value, onChange}: {
    organizerId: IdParam,
    value: number | null,
    onChange: (contactId: number | null) => void,
}) => {
    const [search, setSearch] = useState('');
    const [query] = useDebouncedValue(search, 300);
    const [selectedLabel, setSelectedLabel] = useState<string | null>(null);
    const contactsQuery = useGetCrmContacts(organizerId, {pageNumber: 1, perPage: 10, query}, undefined, query.trim().length > 0);
    const options = [
        {value: YOU, label: t`You`},
        ...(value && selectedLabel ? [{value: String(value), label: selectedLabel}] : []),
        ...(contactsQuery.data?.data ?? [])
            .filter(contact => contact.id !== value)
            .map(contact => ({value: String(contact.id), label: `${contactDisplayName(contact)} · ${contact.email}`})),
    ];

    return (
        <Select
            size="xs"
            className={classes.previewAs}
            aria-label={t`Preview as`}
            leftSection={<span className={classes.previewAsLabel}>{t`As`}</span>}
            leftSectionWidth={30}
            data={options}
            value={value ? String(value) : YOU}
            searchable
            searchValue={search}
            onSearchChange={setSearch}
            nothingFoundMessage={query ? t`No contacts match` : t`Type a name or email`}
            filter={({options: items}) => items}
            onChange={(next, option) => {
                if (!next || next === YOU) {
                    setSelectedLabel(null);
                    onChange(null);
                    return;
                }
                setSelectedLabel(option?.label ?? null);
                onChange(Number(next));
            }}
            allowDeselect={false}
            data-testid="campaign-preview-as"
        />
    );
};

export const PreviewPane = ({
    organizerId,
    isEditable,
    isSent,
    isHidden,
    previewContactId,
    onPreviewContactChange,
    subject,
    preheader,
    fromName,
    html,
    isError,
}: PreviewPaneProps) => {
    const {canUpgrade: canRemoveBranding} = useBrandingSubscription();

    return (
        <div className={classes.previewPane} data-hidden={isHidden || undefined}>
            <EmailPreview
                label={isSent ? t`What was sent` : t`How it will look`}
                controls={isEditable && <PreviewAsContact organizerId={organizerId} value={previewContactId} onChange={onPreviewContactChange}/>}
                banner={isEditable && canRemoveBranding && <BrandingSwitch/>}
                fromName={fromName}
                subject={subject}
                preheader={preheader}
                html={html}
                isError={isError}
                testId="campaign-preview"
            />
        </div>
    );
};
