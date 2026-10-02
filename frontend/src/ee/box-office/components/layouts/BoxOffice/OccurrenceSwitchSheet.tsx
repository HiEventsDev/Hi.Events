import {useState} from "react";
import {Button, Stack, Text} from "@mantine/core";
import {t} from "@lingui/macro";
import {AxiosError} from "axios";
import {BoxOfficePublic, BoxOfficeSession} from "../../../../../types.ts";
import {useUpdateBoxOfficeSession} from "../../../mutations/useUpdateBoxOfficeSession.ts";
import {showError} from "../../../../../utilites/notifications.tsx";
import {OccurrenceSelect} from "../../../../../components/common/OccurrenceSelect";
import {Sheet} from "./Sheet.tsx";

interface OccurrenceSwitchSheetProps {
    opened: boolean;
    onClose: () => void;
    boxOffice: BoxOfficePublic;
    boxOfficeShortId: string;
    session: BoxOfficeSession;
    onUpdated: (session: BoxOfficeSession) => void;
}

export const OccurrenceSwitchSheet = ({opened, onClose, boxOffice, boxOfficeShortId, session, onUpdated}: OccurrenceSwitchSheetProps) => {
    const current = session.event_occurrence?.id ? String(session.event_occurrence.id) : null;
    const [value, setValue] = useState<string | null>(current);
    const updateSession = useUpdateBoxOfficeSession();

    const submit = () => {
        if (!value) return;
        updateSession.mutate({boxOfficeShortId, payload: {event_occurrence_id: Number(value)}}, {
            onSuccess: ({data}) => {
                onUpdated({...session, ...data});
                onClose();
            },
            onError: (error) => {
                showError(error instanceof AxiosError ? (error.response?.data?.message ?? t`Unable to switch date`) : t`Unable to switch date`);
            },
        });
    };

    return (
        <Sheet opened={opened} onClose={onClose} title={t`Change date`}>
            <Stack gap="md">
                <Text size="sm" c="dimmed">{t`Sales, availability and check-in switch to the date you pick.`}</Text>
                <OccurrenceSelect
                    occurrences={boxOffice.occurrences}
                    timezone={boxOffice.event.timezone}
                    value={value}
                    onChange={setValue}
                    label={t`Date`}
                    placeholder={t`Choose a date`}
                    size="md"
                />
                <Button size="lg" fullWidth onClick={submit} loading={updateSession.isPending}
                        disabled={!value || value === current} data-testid="box-office-occurrence-switch-submit">
                    {t`Switch date`}
                </Button>
            </Stack>
        </Sheet>
    );
};
