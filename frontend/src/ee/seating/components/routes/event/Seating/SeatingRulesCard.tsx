import {useState} from "react";
import {Button, Group, NumberInput, Stack, Switch} from "@mantine/core";
import {t} from "@lingui/macro";
import {IdParam} from "../../../../../../types.ts";
import {EventSeatMapRules} from "../../../../api/seat-map.client.ts";
import {useUpdateEventSeatMap} from "../../../../mutations/useUpdateEventSeatMap.ts";
import {showError, showSuccess} from "../../../../../../utilites/notifications.tsx";
import {firstApiError} from "../../../../../../utilites/apiErrors.ts";
import {Card} from "../../../../../../components/common/Card";
import classes from "./Seating.module.scss";

interface SeatingRulesCardProps {
    eventId: IdParam;
    rules: EventSeatMapRules;
    isLocked: boolean;
}

export const SeatingRulesCard = ({eventId, rules, isLocked}: SeatingRulesCardProps) => {
    const mutation = useUpdateEventSeatMap(eventId);
    const [preventOrphans, setPreventOrphans] = useState(rules.prevent_orphan_seats);
    const [maxSeats, setMaxSeats] = useState<number | ''>(rules.max_seats_per_order ?? '');
    const [allowSeatChange, setAllowSeatChange] = useState(rules.allow_seat_change);

    const save = () => mutation.mutateAsync({
        action: 'rules',
        rules: {
            prevent_orphan_seats: preventOrphans,
            max_seats_per_order: maxSeats === '' ? null : maxSeats,
            allow_seat_change: allowSeatChange,
        },
    })
        .then(() => showSuccess(t`Rules saved`))
        .catch(error => showError(firstApiError(error, t`The rules could not be saved`)));

    return (
        <Card>
            <h3 className={classes.sectionTitle}>{t`Selection rules`}</h3>
            <Stack gap="sm" mt="sm">
                <Switch checked={preventOrphans} onChange={event => setPreventOrphans(event.currentTarget.checked)}
                        label={t`Don't let buyers leave a single empty seat`}
                        description={t`A single seat is still allowed when there is no other way to seat the party.`}
                        data-testid="seating-prevent-orphans-switch"/>
                <NumberInput label={t`Maximum seats per order`} placeholder={t`No limit`} min={1} max={100} allowDecimal={false}
                             w={220} value={maxSeats} onChange={value => setMaxSeats(value === '' ? '' : Number(value))}/>
                <Switch checked={allowSeatChange} onChange={event => setAllowSeatChange(event.currentTarget.checked)}
                        label={t`Let buyers change their seat after booking`}
                        description={t`Buyers can move to a free seat in the same band from their ticket page until the event starts.`}
                        data-testid="seating-allow-seat-change-switch"/>
                {!isLocked && (
                    <Group>
                        <Button loading={mutation.isPending} data-testid="seating-save-rules-button" onClick={save}>
                            {t`Save rules`}
                        </Button>
                    </Group>
                )}
            </Stack>
        </Card>
    );
};
