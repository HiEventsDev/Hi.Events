import {Combobox, Group, Input, InputBase, Text, useCombobox} from "@mantine/core";
import {UseFormReturnType} from "@mantine/form";
import React from "react";
import classes from './CustomSelect.module.scss';

export interface ItemProps {
    icon?: React.ReactNode;
    label?: string | undefined;
    description?: string | undefined;
    value?: string | undefined,
    disabled?: boolean,
}

export const SelectOption = ({icon, description, label}: ItemProps) => (
    <Group wrap={'nowrap'} align={'center'}>
        <div style={{marginTop: '5px'}}>{icon}</div>
        <div>
            <Text fz="sm" fw={500}>
                {label}
            </Text>
            <Text fz="xs" opacity={0.6}>
                {description}
            </Text>
        </div>
    </Group>
);

export const CustomSelect = ({optionList, form, name, label, required = false, disabled = false}: {
    optionList: ItemProps[],
    form: UseFormReturnType<any>,
    name: string,
    label?: string,
    required?: boolean,
    disabled?: boolean,
}) => {
    const combobox = useCombobox({
        onDropdownClose: () => combobox.resetSelectedOption(),
    });

    const options = optionList.map((item) => (
        <Combobox.Option value={String(item.value)} key={item.value} disabled={item.disabled}>
            <SelectOption {...item} />
        </Combobox.Option>
    ));

    const selectedOption = optionList.find((item) => item.value === form.values[name])

    return (
        <Combobox
            disabled={disabled}
            store={combobox}
            withinPortal={true}
            position={'bottom-start'}
            // @ts-ignore
            className={disabled && classes.disabled}
            onOptionSubmit={(val) => {
                form.setFieldValue(name, val);
                combobox.closeDropdown();
            }}
        >
            {label && (
                <Input.Label required={required}>
                    {label}
                </Input.Label>
            )}
            <Combobox.Target>
                <InputBase
                    component="div"
                    pointer
                    rightSection={<Combobox.Chevron/>}
                    onClick={() => combobox.toggleDropdown()}
                    rightSectionPointerEvents="none"
                    multiline
                >
                    <SelectOption {...selectedOption} />
                </InputBase>
            </Combobox.Target>

            <Combobox.Dropdown>
                <Combobox.Options>{options}</Combobox.Options>
            </Combobox.Dropdown>
        </Combobox>
    );
};

