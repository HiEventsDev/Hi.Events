import {t} from "@lingui/macro";
import {SegmentedControl, Text} from "@mantine/core";
import {PhoneCollection} from "../../../../types.ts";
import {getPhoneCollectionDescription, getPhoneCollectionLabel} from "../../../../utilites/phoneCollection.tsx";
import classes from "./QuestionsBuilder.module.scss";

interface PhoneCollectionSettingProps {
    value: PhoneCollection;
    disabled: boolean;
    hasPhoneQuestion: boolean;
    onChange: (value: PhoneCollection) => void;
}

const OPTIONS: PhoneCollection[] = ['OFF', 'OPTIONAL', 'REQUIRED'];

export const PhoneCollectionSetting = ({value, disabled, hasPhoneQuestion, onChange}: PhoneCollectionSettingProps) => (
    <div className={classes.phoneCollection}>
        <Text size="sm" fw={500}>{t`Buyer's mobile number`}</Text>
        <SegmentedControl
            size="xs"
            value={value}
            disabled={disabled}
            onChange={(next) => onChange(next as PhoneCollection)}
            data={OPTIONS.map((option) => ({value: option, label: getPhoneCollectionLabel(option)}))}
            data-testid="phone-collection-control"
        />
        <Text size="xs" c="dimmed">{getPhoneCollectionDescription(value)}</Text>
        {value !== 'OFF' && hasPhoneQuestion && (
            <Text size="xs" c="orange.8">
                {t`This event also has a phone number question. Buyers may be asked for a number twice, so remove the question if it asks for the same thing.`}
            </Text>
        )}
    </div>
);
