import {t} from "@lingui/macro";
import {Popover, Select, Switch, Text, UnstyledButton} from "@mantine/core";
import {IconInfoCircle} from "@tabler/icons-react";
import {useAccess} from "../../../../hooks/useAccess.ts";
import {useUpdateEventSettings} from "../../../../mutations/useUpdateEventSettings.ts";
import {EventSettings, IdParam} from "../../../../types.ts";
import {confirmationDialog} from "../../../../utilites/confirmationDialog.tsx";
import {cutoffOptions} from "../../../../utilites/cutoffOptions.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {resaleToggleRefusedMessage, ticketResaleDescription} from "../../../../utilites/ticketResaleCopy.ts";
import classes from "./Resale.module.scss";

const DEFAULT_CUTOFF_MINUTES = 120;

interface ResaleSettingsProps {
    eventId: IdParam;
    eventSettings: EventSettings;
}

export const ResaleSettings = ({eventId, eventSettings}: ResaleSettingsProps) => {
    const canTurnResaleOnOrOff = useAccess().can('ISSUE_REFUNDS');
    const updateMutation = useUpdateEventSettings();
    const isOn = !!eventSettings.allow_ticket_resale;
    const cutoffMinutes = eventSettings.ticket_resale_cutoff_minutes ?? DEFAULT_CUTOFF_MINUTES;

    const save = (changes: Partial<EventSettings>, savedMessage: string) => {
        updateMutation.mutate({eventId, eventSettings: changes}, {
            onSuccess: () => showSuccess(savedMessage),
            onError: (error: any) => showError(error?.response?.data?.message || t`Something went wrong. Please try again.`),
        });
    };

    const confirmToggle = () => {
        if (isOn) {
            confirmationDialog(
                t`Turn off ticket resale?`,
                () => save({allow_ticket_resale: false}, t`Ticket resale is off for this event.`),
                {
                    confirm: t`Turn off resale`,
                    description: t`Listed tickets come off sale and go back to their buyers to use, unless someone is already paying for one. Tickets that have already been resold are not affected.`,
                },
            );
            return;
        }

        confirmationDialog(
            t`Turn on ticket resale?`,
            () => save({allow_ticket_resale: true}, t`Ticket resale is on for this event.`),
            {
                confirm: t`Turn on resale`,
                description: ticketResaleDescription(),
            },
        );
    };

    return (
        <div className={classes.settings} data-testid="resale-settings">
            <Switch
                className={classes.settingsToggle}
                data-testid="resale-enabled-switch"
                label={t`Let buyers resell tickets`}
                description={isOn ? t`Ticket resale is on for this event.` : t`Ticket resale is off for this event.`}
                checked={isOn}
                disabled={!canTurnResaleOnOrOff || updateMutation.isPending}
                title={canTurnResaleOnOrOff ? undefined : resaleToggleRefusedMessage()}
                onChange={confirmToggle}
            />

            <div className={classes.settingsCutoff}>
                <label htmlFor="resale-cutoff" className={classes.settingsCutoffLabel}>{t`Stop resale`}</label>
                <Select
                    id="resale-cutoff"
                    data-testid="resale-cutoff-select"
                    className={classes.settingsCutoffSelect}
                    size="sm"
                    allowDeselect={false}
                    disabled={!isOn || updateMutation.isPending}
                    data={cutoffOptions(cutoffMinutes)}
                    value={String(cutoffMinutes)}
                    onChange={(value) => save({ticket_resale_cutoff_minutes: Number(value ?? DEFAULT_CUTOFF_MINUTES)}, t`Changes saved`)}
                />
            </div>

            <Popover width={360} position="bottom-end" shadow="md" withArrow>
                <Popover.Target>
                    <UnstyledButton className={classes.settingsHelp}>
                        <IconInfoCircle size={16} stroke={1.8} aria-hidden/>
                        {t`How resale works`}
                    </UnstyledButton>
                </Popover.Target>
                <Popover.Dropdown>
                    <Text size="sm" lh={1.5}>{ticketResaleDescription()}</Text>
                </Popover.Dropdown>
            </Popover>
        </div>
    );
};
