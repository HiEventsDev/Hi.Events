import {Badge, Combobox, Group, Input, InputBase, ScrollArea, Stack, Text, useCombobox} from "@mantine/core";
import {UseFormReturnType} from "@mantine/form";
import React from "react";
import {IconCheck} from "@tabler/icons-react";

export interface ItemProps {
    icon?: React.ReactNode;
    label?: string | undefined;
    description?: string | undefined;
    value?: string | undefined;
    disabled?: boolean;
}

interface CustomSelectProps {
    optionList: ItemProps[];
    form: UseFormReturnType<any>;
    name: string;
    label?: string;
    description?: string;
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
    multiple?: boolean;
}

export const SelectOption = ({icon, description, label, selected}: ItemProps & { selected?: boolean }) => (
    <Group wrap="nowrap" align="center" justify="space-between" w="100%">
        <Group wrap="nowrap" align="center">
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
        {selected && <IconCheck size={16} style={{opacity: 0.6}}/>}
    </Group>
);

const MultiValueDisplay = ({selectedOptions}: { selectedOptions: ItemProps[] }) => (
    <Group gap="xs" wrap="wrap">
        {selectedOptions.map((option, index) => (
            <Badge key={index} variant="light" rightSection={<IconCheck size={12}/>}>
                {option.label}
            </Badge>
        ))}
    </Group>
);

export const CustomSelect = ({
                                 optionList,
                                 form,
                                 name,
                                 label,
                                 description,
                                 placeholder,
                                 required = false,
                                 disabled = false,
                                 multiple = false,
                             }: CustomSelectProps) => {
    const combobox = useCombobox({
        onDropdownClose: () => combobox.resetSelectedOption(),
    });

    const selectedValues = multiple ? (form.values[name] || []) : [form.values[name]];
    const error = form.errors[name];

    const options = optionList.map((item) => (
        <Combobox.Option
            value={String(item.value)}
            key={item.value}
            disabled={item.disabled}
            active={selectedValues.includes(item.value)}
        >
            <SelectOption
                {...item}
                selected={selectedValues.includes(item.value)}
            />
        </Combobox.Option>
    ));

    const handleOptionSubmit = (val: string) => {
        if (multiple) {
            const currentValues = form.values[name] || [];
            const newValues = currentValues.includes(val)
                ? currentValues.filter((v: string) => v !== val)
                : [...currentValues, val];
            form.setFieldValue(name, newValues);
        } else {
            form.setFieldValue(name, val);
            combobox.closeDropdown();
        }
    };

    const getSelectedContent = () => {
        if (multiple) {
            const selectedValues = form.values[name] || [];
            const selectedOptions = optionList.filter(item =>
                selectedValues.includes(item.value));
            return selectedOptions.length > 0
                ? <MultiValueDisplay selectedOptions={selectedOptions}/>
                : <Text c="dimmed" fz="sm">{placeholder || 'Select options...'}</Text>;
        }

        const selectedOption = optionList.find(item => item.value === form.values[name]);
        return selectedOption
            ? <SelectOption {...selectedOption} />
            : <Text c="dimmed" fz="sm">{placeholder || 'Select an option...'}</Text>;
    };

    return (
        <Stack
            style={{opacity: disabled ? 0.6 : 1}}
            gap={4}>
            {label && (
                <Input.Label required={required}>
                    {label}
                </Input.Label>
            )}

            {description && (
                <Text size="xs" c="dimmed">
                    {description}
                </Text>
            )}

            <Combobox
                disabled={disabled}
                store={combobox}
                withinPortal={true}
                position="bottom-start"
                onOptionSubmit={handleOptionSubmit}
            >
                <Combobox.Target>
                    <InputBase
                        component="div"
                        pointer
                        error={error}
                        rightSection={<Combobox.Chevron/>}
                        onClick={() => combobox.toggleDropdown()}
                        rightSectionPointerEvents="none"
                        multiline
                    >
                        {getSelectedContent()}
                    </InputBase>
                </Combobox.Target>

                <Combobox.Dropdown>
                    <ScrollArea.Autosize mah={400}>
                        <Combobox.Options>{options}</Combobox.Options>
                    </ScrollArea.Autosize>
                </Combobox.Dropdown>
            </Combobox>
        </Stack>
    );
};
