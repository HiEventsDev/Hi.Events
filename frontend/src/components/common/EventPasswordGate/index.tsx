import {useState} from "react";
import {Button, Paper, PasswordInput, Text, Title} from "@mantine/core";
import {IconLock} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {eventsClientPublic} from "../../../api/event.client.ts";
import {IdParam} from "../../../types.ts";
import classes from "./EventPasswordGate.module.scss";

interface EventPasswordGateProps {
    eventId: IdParam;
    eventTitle: string;
    onSuccess: () => void;
}

export const EventPasswordGate = ({eventId, eventTitle, onSuccess}: EventPasswordGateProps) => {
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setError('');
        setLoading(true);

        try {
            const result = await eventsClientPublic.verifyPassword(eventId, password);
            if (result.data?.valid) {
                localStorage.setItem(`event_unlocked_${eventId}`, '1');
                onSuccess();
            }
        } catch {
            setError(t`Incorrect password. Please try again.`);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className={classes.wrapper}>
            <Paper className={classes.card} shadow="md" p={30} radius="md">
                <div className={classes.iconWrap}>
                    <IconLock size={40}/>
                </div>
                <Title order={3} ta="center" mt="md">
                    {eventTitle}
                </Title>
                <Text c="dimmed" ta="center" mt="xs" size="sm">
                    {t`This event requires a password to access.`}
                </Text>
                <form onSubmit={handleSubmit}>
                    <PasswordInput
                        mt="md"
                        label={t`Event Password`}
                        placeholder={t`Enter the event password`}
                        value={password}
                        onChange={(e) => setPassword(e.currentTarget.value)}
                        error={error}
                        required
                    />
                    <Button type="submit" fullWidth mt="md" loading={loading}>
                        {t`Enter Event`}
                    </Button>
                </form>
            </Paper>
        </div>
    );
};
