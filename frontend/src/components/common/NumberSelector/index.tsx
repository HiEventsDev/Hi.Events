import {ActionIcon, NumberInput, NumberInputHandlers, TextInputProps} from "@mantine/core";
import {useEffect, useRef, useState} from "react";
import {UseFormReturnType} from "@mantine/form";
import {IconMinus, IconPlus} from "@tabler/icons-react";
import classes from './NumberSelector.module.scss';

interface NumberSelectorProps extends TextInputProps {
    formInstance: UseFormReturnType<any>;
    fieldName: string,
}

export const NumberSelector = ({formInstance, fieldName}: NumberSelectorProps) => {
    const handlers = useRef<NumberInputHandlers>(null);
    const [value, setValue] = useState<string | number | ''>(0);

    const min = 0;
    const max = 10;

    useEffect(() => {
        if (value) {
            formInstance.setFieldValue(fieldName, value);
        }
    }, [value]);

    return (
        <div className={classes.wrapper}>
            <ActionIcon<'button'>
                size={28}
                onClick={() => handlers.current?.decrement()}
                disabled={value === min}
                onMouseDown={(event) => event.preventDefault()}
                className={classes.control}
            >
                <IconMinus size="1rem" stroke={1.5}/>
            </ActionIcon>

            <NumberInput
                mb={0}
                variant="unstyled"
                min={min}
                max={max}
                handlersRef={handlers}
                value={value}
                hideControls
                onChange={setValue}
                classNames={{ input: classes.input }}
            />

            <ActionIcon<'button'>
                size={28}
                onClick={() => handlers.current?.increment()}
                disabled={value === max}
                onMouseDown={(event) => event.preventDefault()}
                className={classes.control}
            >
                <IconPlus size="1rem" stroke={1.5}/>
            </ActionIcon>
        </div>
    );
}
