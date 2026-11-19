import {plural, t} from "@lingui/macro";
import {Button, Skeleton} from "@mantine/core";
import {IconTestPipe} from "@tabler/icons-react";
import {Modal, ModalFooter} from "../../../../../components/common/Modal";
import {IdParam} from "../../../../../types.ts";
import {firstApiError} from "../../../../../utilites/apiErrors.ts";
import {formatDateWithLocale} from "../../../../../utilites/dates.ts";
import {showError, showSuccess} from "../../../../../utilites/notifications.tsx";
import {CrmAutomationDraft} from "../../../api/crm.client.ts";
import {useSendTestCrmAutomation} from "../../../mutations/useSendTestCrmAutomation.ts";
import {useGetCrmAutomationPreview} from "../../../queries/useGetCrmAutomationPreview.ts";
import {CrmAutomationPreview} from "../../../types.ts";
import {abandonedWaitLabel} from "../../../utilites/labels.ts";
import sharedClasses from "../../Audience.module.scss";
import classes from "./Automations.module.scss";

interface ConfirmAutomationModalProps {
    organizerId: IdParam;
    draft: CrmAutomationDraft;
    automationId: number | null;
    isRunning: boolean;
    heading: string;
    confirmLabel: string;
    cancelLabel: string;
    timezone: string;
    isConfirming: boolean;
    onConfirm: () => void;
    onClose: () => void;
}

const firstSendDescription = (draft: CrmAutomationDraft, preview: CrmAutomationPreview, timezone: string, isRunning: boolean): string => {
    const {first_send: firstSend} = preview;
    const at = firstSend.at ? formatDateWithLocale(firstSend.at, 'shortDateTime', timezone) : '';
    const title = firstSend.event_title ?? '';
    const date = firstSend.event_date ? formatDateWithLocale(firstSend.event_date, 'shortDate', timezone) : '';

    switch (firstSend.kind) {
        case 'NEXT_PUBLISH':
            return t`It goes out about 30 minutes after you next publish an event. Events that are already on sale won't be announced.`;
        case 'AFTER_DATE':
            return firstSend.at && firstSend.event_date
                ? t`Next send: ${at}, after ${title} on ${date} ends.`
                : t`It goes out after the next date of one of your events ends. Dates that have already ended are skipped.`;
        case 'BEFORE_DATE':
            return firstSend.at && firstSend.event_date
                ? t`Next send: ${at}, before ${title} on ${date}.`
                : t`It goes out before the next date of one of your events. Dates that are too close to send in time are skipped.`;
        case 'AFTER_ABANDON': {
            const wait = abandonedWaitLabel(draft.wait_minutes);
            return t`It goes out about ${wait} after each abandoned checkout.`;
        }
        case 'ON_ENTRY':
            return isRunning
                ? t`Next run: ${at}. People who join get it within 15 minutes.`
                : t`Starts: ${at}. From then on, people who join get it within 15 minutes.`;
        default:
            return isRunning
                ? t`Next run: ${at}. It runs once a day.`
                : t`First run: ${at}. After that it runs once a day.`;
    }
};

const reachDescription = (draft: CrmAutomationDraft, count: number): string => {
    switch (draft.trigger) {
        case 'AFTER_EVENT':
            return plural(count, {one: '# ticket holder who opted in would get the next one.', other: '# ticket holders who opted in would get the next one.'});
        case 'BEFORE_EVENT':
            return plural(count, {one: '# person who opted in would get the next one.', other: '# people who opted in would get the next one.'});
        case 'ABANDONED_CHECKOUT':
            return t`Reaches subscribers who abandon a checkout from now on. Nobody gets it straight away.`;
        case 'SEGMENT_ENTERED':
            return t`Reaches people who join the segment from now on. People already in it won't get it.`;
        case 'WELCOME':
            return t`Reaches people who book with you for the first time from now on. Nobody gets it straight away.`;
        case 'EVENT_PUBLISHED':
            return plural(count, {
                one: '# person who opted in would get the next announcement, as of now.',
                other: '# people who opted in would get the next announcement, as of now.',
            });
        default:
            return plural(count, {
                one: '# person who opted in would get it on the next run, as of now. Nobody gets it more than twice a year.',
                other: '# people who opted in would get it on the next run, as of now. Nobody gets it more than twice a year.',
            });
    }
};

export const ConfirmAutomationModal = ({
    organizerId,
    draft,
    automationId,
    isRunning,
    heading,
    confirmLabel,
    cancelLabel,
    timezone,
    isConfirming,
    onConfirm,
    onClose,
}: ConfirmAutomationModalProps) => {
    const previewQuery = useGetCrmAutomationPreview(organizerId, draft, automationId);
    const sendTest = useSendTestCrmAutomation();
    const preview = previewQuery.data;

    const handleTest = () => sendTest.mutate({organizerId, draft}, {
        onSuccess: response => showSuccess(response.message),
        onError: error => showError(firstApiError(error, t`We couldn't send the test email. Please try again.`)),
    });

    return (
        <Modal opened onClose={onClose} heading={heading} size="lg">
            {previewQuery.isLoading && <Skeleton height={320} radius="md"/>}

            {previewQuery.isError && (
                <p className={sharedClasses.panelHint}>{firstApiError(previewQuery.error, t`We couldn't load a preview of this automation. Please try again.`)}</p>
            )}

            {preview && (
                <div className={classes.confirm}>
                    <ul className={classes.confirmFacts}>
                        <li data-testid="automation-confirm-count">{reachDescription(draft, preview.mailable_count)}</li>
                        <li data-testid="automation-confirm-first-send">{firstSendDescription(draft, preview, timezone, isRunning)}</li>
                    </ul>
                    <div className={classes.confirmSubject}>
                        <span>{t`Subject`}</span>
                        <b>{preview.subject}</b>
                    </div>
                    <iframe
                        className={classes.confirmPreview}
                        title={t`Email preview`}
                        sandbox=""
                        srcDoc={preview.html}
                    />
                    <p className={sharedClasses.panelHint}>
                        {t`The preview uses your own name and a real upcoming event where there is one.`}
                    </p>
                </div>
            )}

            <ModalFooter
                start={(
                    <Button
                        variant="default"
                        leftSection={<IconTestPipe size={16}/>}
                        onClick={handleTest}
                        loading={sendTest.isPending}
                        data-testid="automation-confirm-test"
                    >
                        {t`Send me a test`}
                    </Button>
                )}
            >
                <Button variant="default" onClick={onClose}>{cancelLabel}</Button>
                <Button onClick={onConfirm} loading={isConfirming} disabled={!preview} data-testid="automation-confirm-turn-on">
                    {confirmLabel}
                </Button>
            </ModalFooter>
        </Modal>
    );
};
