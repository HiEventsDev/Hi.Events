import {useState} from "react";
import {plural, t} from "@lingui/macro";
import {Anchor, Autocomplete, Button, Checkbox, FileInput, Text} from "@mantine/core";
import {useForm} from "@mantine/form";
import {IconFileTypeCsv} from "@tabler/icons-react";
import {Modal, ModalFooter} from "../../../../components/common/Modal";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.tsx";
import {IdParam} from "../../../../types.ts";
import {relativeDate} from "../../../../utilites/dates.ts";
import {useImportCrmContacts} from "../../mutations/useImportCrmContacts.ts";
import {useGetCrmImports} from "../../queries/useGetCrmImports.ts";
import {useGetCrmTags} from "../../queries/useGetCrmTags.ts";
import {CrmContactImport} from "../../types.ts";
import {ImportBreakdown} from "../ImportBreakdown";
import {ImportOutcomeModal, importProgressLabel, isPartlyImported} from "../ImportProgress";
import classes from "./ImportContactsModal.module.scss";

interface ImportContactsModalProps {
    organizerId: IdParam;
    onImportSuppressions: () => void;
    onClose: () => void;
}

const summary = (result: CrmContactImport): string => (result.kind === 'SUPPRESSIONS' ? [
    t`Suppression list`,
    plural(result.created_count + result.updated_count, {one: '# contact suppressed', other: '# contacts suppressed'}),
    result.skipped_count > 0 && plural(result.skipped_count, {one: '# row skipped', other: '# rows skipped'}),
] : [
    plural(result.created_count, {one: '# contact added', other: '# contacts added'}),
    result.updated_count > 0 && plural(result.updated_count, {one: '# already existed', other: '# already existed'}),
    result.skipped_count > 0 && plural(result.skipped_count, {one: '# row skipped', other: '# rows skipped'}),
]).filter(Boolean).join(' · ');

const recentStatus = (entry: CrmContactImport): string => {
    if (entry.status === 'PROCESSING') {
        return importProgressLabel(entry);
    }
    if (entry.status === 'FAILED') {
        return isPartlyImported(entry) ? t`Stopped partway · ${summary(entry)}` : t`Failed`;
    }
    return summary(entry);
};

const ImportResult = ({result}: { result: CrmContactImport }) => {
    const reasons = result.skipped_reasons;
    const totalRows = result.total_rows;
    const rows = [
        {label: t`Added to your audience`, value: result.created_count},
        {label: t`Already in your audience`, value: result.updated_count},
        ...(reasons ? [
            {label: t`Skipped: not a valid email address`, value: reasons.invalid_email},
            {label: t`Skipped: listed more than once in the file`, value: reasons.duplicate_in_file},
            {label: t`Skipped: deleted from your audience earlier`, value: reasons.previously_deleted},
        ] : [{label: t`Skipped`, value: result.skipped_count}]),
    ];

    return (
        <div className={classes.result} data-testid="audience-import-result">
            <Text fw={500}>{plural(totalRows, {one: '# row in the file', other: '# rows in the file'})}</Text>
            <ImportBreakdown rows={rows}/>
            {!!reasons?.previously_deleted && (
                <Text size="sm" c="dimmed">
                    {t`People you deleted aren't added back by imports. Add them one at a time if they asked to rejoin.`}
                </Text>
            )}
        </div>
    );
};

const RecentImport = ({entry}: { entry: CrmContactImport }) => {
    const when = relativeDate(entry.created_at);
    const userName = entry.user_name;

    return (
        <li className={classes.recentItem}>
            <span className={classes.recentFile}>{entry.file_name}</span>
            <span className={classes.recentMeta}>
                {recentStatus(entry)}
                {' · '}
                {userName ? t`${when} by ${userName}` : when}
            </span>
        </li>
    );
};

export const RecentImports = ({organizerId}: { organizerId: IdParam }) => {
    const importsQuery = useGetCrmImports(organizerId);
    const imports = importsQuery.data ?? [];

    if (imports.length === 0) {
        return null;
    }

    return (
        <div className={classes.recent}>
            <Text size="sm" fw={500}>{t`Recent imports`}</Text>
            <ul className={classes.recentList}>
                {imports.map(entry => <RecentImport key={entry.id} entry={entry}/>)}
            </ul>
        </div>
    );
};

export const ImportContactsModal = ({organizerId, onImportSuppressions, onClose}: ImportContactsModalProps) => {
    const importContacts = useImportCrmContacts();
    const errorHandler = useFormErrorResponseHandler();
    const tagsQuery = useGetCrmTags(organizerId);
    const [result, setResult] = useState<CrmContactImport | null>(null);
    const form = useForm({
        initialValues: {file: null as File | null, tag_name: '', consent_attested: false},
    });

    const handleImport = (values: typeof form.values) => {
        if (!values.file) {
            return;
        }
        importContacts.mutate({
            organizerId,
            file: values.file,
            consentAttested: values.consent_attested,
            tagName: values.tag_name,
        }, {
            onSuccess: response => setResult(response.data),
            onError: error => errorHandler(form, error),
        });
    };

    if (result) {
        return (
            <ImportOutcomeModal
                organizerId={organizerId}
                started={result}
                finishedHeading={t`Import finished`}
                doneTestId="audience-import-done"
                renderResult={entry => <ImportResult result={entry}/>}
                onImportAnother={() => {
                    setResult(null);
                    form.reset();
                }}
                onClose={onClose}
            />
        );
    }

    return (
        <Modal opened onClose={onClose} heading={t`Import contacts`} size="md">
            <form onSubmit={form.onSubmit(handleImport)}>
                <FileInput
                    {...form.getInputProps('file')}
                    label={t`CSV file`}
                    description={t`Needs a header row with an Email column. First name and Last name columns are optional. Up to 20,000 rows.`}
                    placeholder={t`Choose a file`}
                    accept=".csv,text/csv"
                    leftSection={<IconFileTypeCsv size={18}/>}
                    clearable
                />
                <Autocomplete
                    {...form.getInputProps('tag_name')}
                    data={(tagsQuery.data ?? []).map(tag => tag.name)}
                    label={t`Tag these contacts`}
                    description={t`Optional. Pick a tag or type a new one. Makes it easy to find this list again.`}
                    placeholder={t`Newsletter`}
                    maxLength={50}
                />
                <Checkbox
                    {...form.getInputProps('consent_attested', {type: 'checkbox'})}
                    label={t`These people opted in to marketing emails from us`}
                    description={t`Only tick this if each person agreed to hear from you. It applies to people who are new to your audience; people already here keep their current choice. Without it, the contacts are added but campaigns skip them.`}
                />
                <Text size="sm" c="dimmed" mt="sm">
                    {t`Moving people who unsubscribed or bounced from another tool?`}{' '}
                    <Anchor className={classes.suppressionLink} component="button" type="button" size="sm" onClick={onImportSuppressions}>
                        {t`Import a suppression list instead`}
                    </Anchor>
                </Text>
                <RecentImports organizerId={organizerId}/>
                <ModalFooter>
                    <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                    <Button type="submit" disabled={!form.values.file} loading={importContacts.isPending} data-testid="audience-import-submit">
                        {t`Import`}
                    </Button>
                </ModalFooter>
            </form>
        </Modal>
    );
};
