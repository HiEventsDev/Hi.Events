import {Badge, Group, Radio, Stack, Text} from "@mantine/core";
import {t} from "@lingui/macro";
import {TerminalReader} from "../../../../../types.ts";

export const NO_READER = 'none';

interface ReaderPickerProps {
    readers: TerminalReader[];
    value: string;
    onChange: (value: string) => void;
    label?: string;
    description?: string;
}

const statusLabel = (status: string): string =>
    status === 'online' ? t`Online` : status === 'offline' ? t`Offline` : t`Unknown`;

export const ReaderPicker = ({readers, value, onChange, label, description}: ReaderPickerProps) => (
    <Radio.Group label={label} description={description} value={value} onChange={onChange}>
        <Stack gap="xs" mt="xs">
            {readers.map(reader => (
                <Radio.Card key={reader.id} value={String(reader.id)} radius="md" p="sm"
                            data-testid={`box-office-reader-${reader.id}`}>
                    <Group justify="space-between" gap="sm">
                        <Group gap="sm">
                            <Radio.Indicator/>
                            <Text fw={600} size="sm">{reader.label}</Text>
                        </Group>
                        <Badge color={reader.status === 'online' ? 'green' : 'gray'} variant="light">
                            {statusLabel(reader.status)}
                        </Badge>
                    </Group>
                </Radio.Card>
            ))}
            <Radio.Card value={NO_READER} radius="md" p="sm" data-testid="box-office-reader-none">
                <Group gap="sm">
                    <Radio.Indicator/>
                    <Text fw={600} size="sm">{t`No reader (cash only)`}</Text>
                </Group>
            </Radio.Card>
        </Stack>
    </Radio.Group>
);
