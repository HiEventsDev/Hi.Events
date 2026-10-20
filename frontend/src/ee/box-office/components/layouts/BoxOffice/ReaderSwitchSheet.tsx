import {useEffect, useState} from "react";
import {Button, Stack, Text} from "@mantine/core";
import {t} from "@lingui/macro";
import {AxiosError} from "axios";
import {BoxOfficeSession} from "../../../../../types.ts";
import {useGetBoxOfficePublic} from "../../../queries/useGetBoxOfficePublic.ts";
import {useUpdateBoxOfficeSession} from "../../../mutations/useUpdateBoxOfficeSession.ts";
import {rememberReaderId} from "../../../utilites/boxOfficeSession.ts";
import {showError} from "../../../../../utilites/notifications.tsx";
import {NO_READER, ReaderPicker} from "./ReaderPicker.tsx";
import {Sheet} from "./Sheet.tsx";

interface ReaderSwitchSheetProps {
    opened: boolean;
    onClose: () => void;
    boxOfficeShortId: string;
    session: BoxOfficeSession;
    onUpdated: (session: BoxOfficeSession) => void;
}

export const ReaderSwitchSheet = ({opened, onClose, boxOfficeShortId, session, onUpdated}: ReaderSwitchSheetProps) => {
    const boxOfficeQuery = useGetBoxOfficePublic(boxOfficeShortId);
    const readers = boxOfficeQuery.data?.data.readers ?? [];
    const current = session.reader ? String(session.reader.id) : NO_READER;
    const [value, setValue] = useState(current);
    const updateSession = useUpdateBoxOfficeSession();

    useEffect(() => {
        boxOfficeQuery.refetch();
    }, []);

    const submit = () => {
        const readerId = value === NO_READER ? null : Number(value);
        updateSession.mutate({boxOfficeShortId, payload: {stripe_terminal_reader_id: readerId}}, {
            onSuccess: ({data}) => {
                rememberReaderId(boxOfficeShortId, readerId === null ? null : String(readerId));
                onUpdated({...session, ...data, reader: data.reader});
                onClose();
            },
            onError: (error) => {
                showError(error instanceof AxiosError ? (error.response?.data?.message ?? t`Unable to change reader`) : t`Unable to change reader`);
            },
        });
    };

    return (
        <Sheet opened={opened} onClose={onClose} title={t`Card reader`}>
            <Stack gap="md">
                {readers.length === 0 ? (
                    <Text size="sm" c="dimmed">{t`No card readers are paired to this organizer. Pair one under Settings › Card readers.`}</Text>
                ) : (
                    <ReaderPicker
                        readers={readers}
                        value={value}
                        onChange={setValue}
                        description={t`Pick the reader next to you, or sell cash only`}
                    />
                )}
                <Button variant="subtle" size="xs" onClick={() => boxOfficeQuery.refetch()} loading={boxOfficeQuery.isFetching}>
                    {t`Refresh reader status`}
                </Button>
                <Button size="lg" fullWidth onClick={submit} loading={updateSession.isPending} disabled={value === current}
                        data-testid="box-office-reader-switch-submit">
                    {value === NO_READER ? t`Sell cash only` : t`Use this reader`}
                </Button>
            </Stack>
        </Sheet>
    );
};
