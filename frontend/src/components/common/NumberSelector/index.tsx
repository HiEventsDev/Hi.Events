import {ActionIcon, NumberInput, NumberInputHandlers, Select, TextInputProps} from "@mantine/core";
import {useEffect, useRef, useState} from "react";
import {UseFormReturnType} from "@mantine/form";
import {IconMinus, IconPlus} from "@tabler/icons-react";
import classes from './NumberSelector.module.scss';
import classNames from "classnames";
import _ from "lodash";

interface NumberSelectorProps extends TextInputProps {
    formInstance: UseFormReturnType<any>;
    fieldName: string,
    min?: number;
    max?: number;
    sharedValues?: SharedValues;
}

export const NumberSelector = ({formInstance, fieldName, min, max, sharedValues}: NumberSelectorProps) => {
    const handlers = useRef<NumberInputHandlers>(null);
    // Start with 0, ensuring it's treated as number for consistency
    const [value, setValue] = useState<number>(0);

    const minValue = min || 0;
    const maxValue = max || 100;

    const [sharedVals] = useState<SharedValues>(sharedValues ?? new SharedValues(maxValue));

    useEffect(() => {
        formInstance.setFieldValue(fieldName, value);
    }, [value]);

    useEffect(() => {
        // to handle application promo code after updating the quanity 
        const formValue = _.get(formInstance.values, fieldName)
        if (formValue !== value) {
            formInstance.setFieldValue(fieldName, value);
        }
    }, [formInstance]);

    const increment = () => {
        // Adjust from 0 to minValue on the first increment, if minValue is greater than 0
        if (value === 0 && minValue > 1) {
            // If incrementing from 0, we have a few scenarios:
            // 1. If there is sufficient quantity, increment to the minValue
            // 2. If there is insufficient quantity to reach minValue, increment to the remaining quantity
            // 3. If another NumberSelector is sharing this NumberSelector's SharedValues, and the amount
            //    selected on that NumberSelector is less than minValue, increment to an amount where the
            //    combined count across the NumberSelectors is minValue (or at least 1)
            let adjustedMinimum = Math.max(1, minValue - sharedVals.currentValue)
            setValue(sharedVals.changeValue(Math.min(adjustedMinimum, maxValue, sharedVals.quantityRemaining)))
        } else if (sharedVals.currentValue < minValue) {
            setValue(prevValue => prevValue + (sharedVals.changeValue(minValue - sharedVals.currentValue)))
        } else if (value < maxValue) {
            setValue(prevValue => prevValue + sharedVals.changeValue(1));
        }
    };

    const decrement = () => {
        // Ensure decrement does not bring the current shared value between 0 and minValue
        if (sharedVals.currentValue > minValue) {
            setValue(prevValue => prevValue + sharedVals.changeValue(-1));
        } else {
            sharedVals.changeValue(-value)
            setValue(0);
        }
    };

    const changeValue = (newValue: number) => {
        let adjustedDifference = sharedVals.changeValue(newValue - value);
        setValue(value + adjustedDifference);
    };

    return (
        <div className={classNames(classes.wrapper, 'button-input')}>
            <ActionIcon
                size={28}
                onClick={decrement}
                disabled={value === 0}
                onMouseDown={(event) => event.preventDefault()}
                className={classes.control}
            >
                <IconMinus size="1rem" stroke={1.5}/>
            </ActionIcon>

            <NumberInput
                mb={0}
                variant="unstyled"
                min={minValue}
                max={maxValue}
                handlersRef={handlers}
                value={value}
                hideControls
                onChange={changeValue}
                classNames={{input: classes.input}}
            />

            <ActionIcon
                size={28}
                onClick={increment}
                disabled={value >= maxValue || sharedVals.quantityRemaining == 0}
                onMouseDown={(event) => event.preventDefault()}
                className={classes.control}
            >
                <IconPlus size="1rem" stroke={1.5}/>
            </ActionIcon>
        </div>
    );
}

/* todo: create an event setting to choose select over button */
export const NumberSelectorSelect = ({formInstance, fieldName, min, max, className}: NumberSelectorProps) => {
    const [value, setValue] = useState<string>('0');

    const minValue = min || 0;
    const maxValue = max || 100;

    useEffect(() => {
        // Only synchronize with form if the value is within bounds and not 0
        if (value !== '0') {
            formInstance.setFieldValue(fieldName, value);
        }
    }, [value, formInstance, fieldName]);

    let data = Array.from({length: maxValue - minValue + 1}, (_, i) => ({
        label: String(minValue + i),
        value: String(minValue + i),
    }));

    if (minValue > 0) {
        data = [{label: '0', value: '0'}, ...data];
    }

    return (
        <div className={classNames(classes.wrapper, 'select-input')}>
            <Select
                classNames={{
                    input: classes.input,
                }}
                className={className}
                onChange={(value) => setValue(value ?? '0')} // Ensure the value is set correctly on change
                value={value}
                data={data}
                checkIconPosition="right"
            />
        </div>
    );
}

// Used to aggregate related NumberSelectors together, to allow them to share a common maximum
// and know about the collective values of all the selectors
export class SharedValues {
    sharedMax: number;
    currentValue: number;

    constructor(sharedMax: number) {
        this.sharedMax = sharedMax;
        this.currentValue = 0;
    }

    get quantityRemaining() {
        return this.sharedMax - this.currentValue;
    }

    changeValue(difference: number) {
        let adjustedDifference = Math.min(difference, this.sharedMax - this.currentValue);
        this.currentValue += adjustedDifference;

        return adjustedDifference;
    }
}

