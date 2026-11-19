import {useState} from "react";
import {t} from "@lingui/macro";
import {Avatar, SegmentedControl, Select} from "@mantine/core";
import {useDebouncedValue} from "@mantine/hooks";
import {IconDeviceDesktop, IconDeviceMobile} from "@tabler/icons-react";
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
    const [device, setDevice] = useState<'desktop' | 'phone'>('desktop');

    return (
        <div className={classes.previewPane} data-hidden={isHidden || undefined}>
            <div className={classes.previewBar}>
                <span>{isSent ? t`What was sent` : t`How it will look`}</span>
                <div className={classes.previewControls}>
                    {isEditable && <PreviewAsContact organizerId={organizerId} value={previewContactId} onChange={onPreviewContactChange}/>}
                    <SegmentedControl
                        className={classes.deviceSwitch}
                        size="xs"
                        value={device}
                        onChange={value => setDevice(value === 'phone' ? 'phone' : 'desktop')}
                        data={[
                            {value: 'desktop', label: <IconDeviceDesktop size={14} aria-label={t`Desktop`}/>},
                            {value: 'phone', label: <IconDeviceMobile size={14} aria-label={t`Phone`}/>},
                        ]}
                    />
                </div>
            </div>
            <div className={classes.inbox}>
                <Avatar size={32} radius="xl" color="primary">{fromName.slice(0, 1).toUpperCase()}</Avatar>
                <span className={classes.inboxText} data-testid="campaign-preview-inbox">
                    <span>{fromName}</span>
                    <strong>{subject || t`Your subject line`}</strong>
                    {preheader && <span className={classes.inboxPreheader}>{preheader}</span>}
                </span>
            </div>
            <div className={classes.previewStage}>
                {html ? (
                    <iframe
                        className={classes.previewFrame}
                        data-device={device}
                        title={t`Email preview`}
                        sandbox=""
                        srcDoc={html}
                        data-testid="campaign-preview"
                    />
                ) : (
                    <div className={classes.previewEmpty}>
                        {isError ? t`We couldn't load the preview. Please try again.` : t`Loading preview…`}
                    </div>
                )}
            </div>
        </div>
    );
};
