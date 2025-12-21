import {t} from "@lingui/macro";
import {Switch} from "@mantine/core";

interface SelfServiceSettingsProps {
    value: boolean;
    onChange: (value: boolean) => void;
    disabled?: boolean;
    isDefault?: boolean;
}

export const SelfServiceSettings = ({
    value,
    onChange,
    disabled = false,
    isDefault = false,
}: SelfServiceSettingsProps) => {
    const label = isDefault
        ? t`Enable attendee self-service by default`
        : t`Enable attendee self-service`;

    const description = isDefault
        ? t`When enabled, new events will allow attendees to manage their own ticket details via a secure link. This can be overridden per event.`
        : t`Allow attendees to update their ticket information (name, email) via a secure link sent with their order confirmation.`;

    return (
        <Switch
            label={label}
            description={description}
            checked={value}
            onChange={(event) => onChange(event.currentTarget.checked)}
            disabled={disabled}
        />
    );
};
