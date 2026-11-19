import {ReactNode} from "react";
import {plural, t} from "@lingui/macro";
import {Button, Progress, Text} from "@mantine/core";
import {Modal, ModalFooter} from "../../../../components/common/Modal";
import {IdParam} from "../../../../types.ts";
import {useGetCrmImports} from "../../queries/useGetCrmImports.ts";
import {CrmContactImport} from "../../types.ts";
import {formatCount} from "../../utilites/format.ts";
import classes from "./ImportProgress.module.scss";
import {Notice} from "../Notice";

const rowsHandled = (entry: CrmContactImport): number => entry.created_count + entry.updated_count + entry.skipped_count;

export const importProgressLabel = (entry: CrmContactImport): string => {
    const handled = formatCount(rowsHandled(entry));

    return plural(entry.total_rows, {one: `Importing… ${handled} of # row`, other: `Importing… ${handled} of # rows`});
};

export const isPartlyImported = (entry: CrmContactImport): boolean =>
    entry.status === 'FAILED' && entry.created_count + entry.updated_count > 0;

interface ImportOutcomeModalProps {
    organizerId: IdParam;
    started: CrmContactImport;
    finishedHeading: string;
    doneTestId: string;
    renderResult: (entry: CrmContactImport) => ReactNode;
    onImportAnother: () => void;
    onClose: () => void;
}

export const ImportOutcomeModal = ({organizerId, started, finishedHeading, doneTestId, renderResult, onImportAnother, onClose}: ImportOutcomeModalProps) => {
    const importsQuery = useGetCrmImports(organizerId);
    const entry = importsQuery.data?.find(candidate => candidate.id === started.id) ?? started;

    if (entry.status === 'PROCESSING') {
        return (
            <Modal opened onClose={onClose} heading={t`Import in progress`} size="md">
                <div className={classes.progress} data-testid="audience-import-progress">
                    <Text fw={500} role="status">{importProgressLabel(entry)}</Text>
                    <Progress
                        value={Math.max(rowsHandled(entry) / Math.max(entry.total_rows, 1) * 100, 4)}
                        animated
                        aria-label={t`Progress`}
                    />
                    <Text size="sm" c="dimmed">
                        {t`Large files are imported in the background. You can close this window: your contacts list updates when the import is done, and the result is kept under Recent imports.`}
                    </Text>
                    {importsQuery.isError && (
                        <Notice>
                            {t`We can't check on this import right now. It carries on in the background; open Import contacts again in a few minutes to see the result.`}
                        </Notice>
                    )}
                </div>
                <ModalFooter>
                    <Button onClick={onClose} data-testid={doneTestId}>{t`Close`}</Button>
                </ModalFooter>
            </Modal>
        );
    }

    const hasFailed = entry.status === 'FAILED';

    return (
        <Modal opened onClose={onClose} heading={hasFailed ? t`Import didn't finish` : finishedHeading} size="md">
            {hasFailed && (
                <Notice tone="danger">
                    {isPartlyImported(entry)
                        ? t`Something went wrong partway through, so only part of this file was imported. Import the same file again to finish: rows that are already in don't create duplicates.`
                        : t`Something went wrong and nothing from this file was imported. Please try again.`}
                </Notice>
            )}
            {(!hasFailed || isPartlyImported(entry)) && renderResult(entry)}
            <ModalFooter>
                <Button variant="default" onClick={onImportAnother}>
                    {hasFailed ? t`Try again` : t`Import another file`}
                </Button>
                <Button onClick={onClose} data-testid={doneTestId}>{t`Done`}</Button>
            </ModalFooter>
        </Modal>
    );
};
