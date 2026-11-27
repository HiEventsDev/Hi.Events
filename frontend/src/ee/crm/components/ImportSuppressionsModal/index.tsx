import {useState} from "react";
import {plural, t} from "@lingui/macro";
import {Button, FileInput, Loader, Text} from "@mantine/core";
import {IconFileTypeCsv} from "@tabler/icons-react";
import {Modal, ModalFooter} from "../../../../components/common/Modal";
import {IdParam} from "../../../../types.ts";
import {firstApiError} from "../../../../utilites/apiErrors.ts";
import {useImportCrmSuppressions, usePreviewCrmSuppressionImport} from "../../mutations/useCrmSuppressionMutations.ts";
import {CrmContactImport, CrmSuppressionImportPreview} from "../../types.ts";
import {SUPPRESSION_REASONS, suppressionReasonLabel} from "../../utilites/suppression.ts";
import {ImportBreakdown} from "../ImportBreakdown";
import {RecentImports} from "../ImportContactsModal";
import {ImportOutcomeModal} from "../ImportProgress";
import classes from "./ImportSuppressionsModal.module.scss";
import {Notice} from "../Notice";

const PreviewSummary = ({preview}: {preview: CrmSuppressionImportPreview}) => {
    const subscribed = preview.subscribed_contacts;

    return (
        <div className={classes.preview} data-testid="audience-suppression-preview">
            <table className={classes.sample}>
                <thead>
                    <tr>
                        <th>{t`Email`}</th>
                        <th>{t`Reason`}</th>
                    </tr>
                </thead>
                <tbody>
                    {preview.sample.map(row => (
                        <tr key={row.email}>
                            <td>{row.email}</td>
                            <td>{suppressionReasonLabel(row.reason)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
            {preview.valid_rows > preview.sample.length && (
                <Text size="xs" c="dimmed">{plural(preview.valid_rows - preview.sample.length, {one: 'and # more address', other: 'and # more addresses'})}</Text>
            )}

            {subscribed > 0 && (
                <div data-testid="audience-suppression-warning">
                    <Notice>
                        {plural(subscribed, {one: '# subscribed contact will stop getting campaigns.', other: '# subscribed contacts will stop getting campaigns.'})}
                    </Notice>
                </div>
            )}

            <ImportBreakdown rows={[
                ...SUPPRESSION_REASONS.map(reason => ({label: suppressionReasonLabel(reason), value: preview.reasons[reason]})),
                {label: t`Already in your audience`, value: preview.existing_contacts},
                {label: t`New, added as suppressed contacts`, value: preview.new_contacts},
                {label: t`Deleted earlier: they stay deleted and suppressed`, value: preview.deleted_contacts},
                {label: t`Skipped: not a valid email address`, value: preview.invalid_rows},
                {label: t`Skipped: listed more than once in the file`, value: preview.duplicate_rows},
            ]}/>
        </div>
    );
};

const ImportResult = ({result}: {result: CrmContactImport}) => {
    const reasons = result.skipped_reasons;

    return (
        <div className={classes.preview} data-testid="audience-suppression-import-result">
            <Text fw={500}>{plural(result.total_rows, {one: '# row in the file', other: '# rows in the file'})}</Text>
            <ImportBreakdown rows={[
                {label: t`Contacts suppressed`, value: result.updated_count},
                {label: t`New contacts added as suppressed`, value: result.created_count},
                {label: t`Already suppressed`, value: reasons?.already_suppressed ?? 0},
                {label: t`Bounced, but not the contact's main address: their status is unchanged`, value: reasons?.bounced_secondary_address ?? 0},
                {label: t`Deleted contacts kept suppressed`, value: reasons?.previously_deleted ?? 0},
                {label: t`Skipped: not a valid email address`, value: reasons?.invalid_email ?? 0},
                {label: t`Skipped: listed more than once in the file`, value: reasons?.duplicate_in_file ?? 0},
            ]}/>
        </div>
    );
};

export const ImportSuppressionsModal = ({organizerId, onClose}: {organizerId: IdParam, onClose: () => void}) => {
    const preview = usePreviewCrmSuppressionImport();
    const importSuppressions = useImportCrmSuppressions();
    const [file, setFile] = useState<File | null>(null);
    const [result, setResult] = useState<CrmContactImport | null>(null);

    const chooseFile = (next: File | null) => {
        setFile(next);
        preview.reset();
        if (next) {
            preview.mutate({organizerId, file: next});
        }
    };

    const runImport = () => file && importSuppressions.mutate({organizerId, file}, {
        onSuccess: ({data}) => setResult(data),
    });

    if (result) {
        return (
            <ImportOutcomeModal
                organizerId={organizerId}
                started={result}
                finishedHeading={t`Suppression list imported`}
                doneTestId="audience-suppression-import-done"
                renderResult={entry => <ImportResult result={entry}/>}
                onImportAnother={() => {
                    setResult(null);
                    setFile(null);
                    preview.reset();
                    importSuppressions.reset();
                }}
                onClose={onClose}
            />
        );
    }

    return (
        <Modal opened onClose={onClose} heading={t`Import suppressions`} size="md">
            <Text size="sm" c="dimmed" mb="md">
                {t`Bring over the people other tools couldn't email, so you never email them by mistake. Campaigns skip everyone on this list, and nobody is opted in.`}
            </Text>
            <FileInput
                label={t`CSV file`}
                description={t`Needs an Email column. An optional Reason column takes unsubscribed, bounced (or cleaned) and complained (or spam). Rows without a reason count as unsubscribed.`}
                placeholder={t`Choose a file`}
                accept=".csv,text/csv"
                leftSection={<IconFileTypeCsv size={18}/>}
                value={file}
                onChange={chooseFile}
                error={preview.isError ? firstApiError(preview.error, t`We couldn't read that file. Check it's a CSV and try again.`) : importSuppressions.isError ? firstApiError(importSuppressions.error, t`We couldn't import that file. Please try again.`) : undefined}
                clearable
            />

            {preview.isPending && <div className={classes.loading}><Loader size="sm"/></div>}
            {preview.data && <PreviewSummary preview={preview.data.data}/>}

            <RecentImports organizerId={organizerId}/>

            <ModalFooter>
                <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                <Button
                    onClick={runImport}
                    disabled={!preview.data}
                    loading={importSuppressions.isPending}
                    data-testid="audience-suppression-import-submit"
                >
                    {preview.data
                        ? plural(preview.data.data.valid_rows, {one: 'Suppress # address', other: 'Suppress # addresses'})
                        : t`Import`}
                </Button>
            </ModalFooter>
        </Modal>
    );
};
