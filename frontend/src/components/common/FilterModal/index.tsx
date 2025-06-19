import React from 'react';
import {Button, Group, Modal, MultiSelect, Stack, Text, TextInput} from '@mantine/core';
import {useDisclosure} from '@mantine/hooks';
import {IconFilter} from '@tabler/icons-react';
import {t} from '@lingui/macro';

export interface FilterOption {
    field: string;
    label: string;
    type: 'multi-select' | 'date-range' | 'single-select' | 'text';
    options?: { label: string; value: string }[];
}

interface FilterValues {
    [key: string]: any;
}

interface FilterModalProps {
    filters: FilterOption[];
    activeFilters: FilterValues;
    onChange: (values: FilterValues) => void;
    onReset?: () => void;
    title?: string;
}

const normalizeFilterValue = (value: any, type: string): any => {
    if (value === undefined || value === null) {
        return [];
    }

    switch (type) {
        case 'multi-select': {
            if (Array.isArray(value)) {
                return value;
            }

            if (typeof value === 'string') {
                return value.split(',').filter(Boolean).map(item => item.trim());
            }

            if (value?.value) {
                if (Array.isArray(value.value)) {
                    return value.value;
                }
                return normalizeFilterValue(value.value, type);
            }

            return [];
        }

        case 'text': {
            return value;
        }

        default: {
            return value;
        }
    }
};

const normalizeFilters = (filters: FilterOption[], values: FilterValues): FilterValues => {
    return filters.reduce((acc, filter) => {
        return {
            ...acc,
            [filter.field]: normalizeFilterValue(values[filter.field], filter.type)
        };
    }, {});
};

export const FilterModal: React.FC<FilterModalProps> = ({
                                                            filters,
                                                            activeFilters,
                                                            onChange,
                                                            onReset,
                                                            title = t`Filters`
                                                        }) => {
    const [opened, {open, close}] = useDisclosure(false);
    const [localFilters, setLocalFilters] = React.useState<FilterValues>(() => {
        return normalizeFilters(filters, activeFilters);
    });

    React.useEffect(() => {
        if (!opened) {
            setLocalFilters(normalizeFilters(filters, activeFilters));
        }
    }, [activeFilters, filters, opened]);

    const handleSave = () => {
        onChange(localFilters);
        close();
    };

    const handleReset = () => {
        const emptyFilters = filters.reduce((acc, filter) => {
            let emptyValue;

            switch (filter.type) {
                case 'multi-select': {
                    emptyValue = [];
                    break;
                }
                case 'text': {
                    emptyValue = '';
                    break;
                }
                case 'single-select': {
                    emptyValue = null;
                    break;
                }
                case 'date-range': {
                    emptyValue = {start: null, end: null};
                    break;
                }
                default: {
                    emptyValue = null;
                }
            }

            return {
                ...acc,
                [filter.field]: emptyValue
            };
        }, {});

        if (onReset) {
            onReset();
        }

        setLocalFilters(emptyFilters);
        onChange(emptyFilters);
        close();
    };

    const handleFilterChange = (field: string, value: any) => {
        setLocalFilters(prev => {
            return {
                ...prev,
                [field]: value,
            };
        });
    };

    const renderFilterInput = (filter: FilterOption) => {
        const normalizedValue = normalizeFilterValue(localFilters[filter.field], filter.type);

        switch (filter.type) {
            case 'multi-select': {
                return (
                    <MultiSelect
                        placeholder={t`Select ${filter.label.toLowerCase()}`}
                        key={filter.field}
                        label={filter.label}
                        data={filter.options || []}
                        value={normalizedValue}
                        onChange={(value) => {
                            handleFilterChange(filter.field, value || []);
                        }}
                        clearable
                        searchable
                        w="100%"
                        style={{marginBottom: 0}}
                    />
                );
            }

            case 'text': {
                return (
                    <TextInput
                        key={filter.field}
                        label={filter.label}
                        value={normalizedValue}
                        onChange={(event) => {
                            handleFilterChange(filter.field, event.currentTarget.value);
                        }}
                        w="100%"
                    />
                );
            }

            default: {
                return null;
            }
        }
    };

    const countActiveFilters = (filterValues: FilterValues, filterOptions: FilterOption[]): number => {
        return Object.entries(filterValues).reduce((count, [field, value]) => {
            const filterOption = filterOptions.find(f => f.field === field);

            if (!filterOption) {
                return count;
            }

            const normalizedValue = normalizeFilterValue(value, filterOption.type);

            if (Array.isArray(normalizedValue)) {
                if (normalizedValue.length > 0) {
                    return count + 1;
                }
                return count;
            }

            if (normalizedValue) {
                return count + 1;
            }

            return count;
        }, 0);
    };

    const activeFilterCount = countActiveFilters(activeFilters, filters);
    const hasActiveFilters = activeFilterCount > 0;

    return (
        <>
            <Button
                variant={hasActiveFilters ? 'outline' : 'light'}
                color={hasActiveFilters ? 'primary' : 'gray'}
                leftSection={<IconFilter size={16}/>}
                onClick={open}
                size="sm"
            >
                {hasActiveFilters ? t`Filters (${activeFilterCount})` : t`Filters`}
            </Button>

            <Modal opened={opened} onClose={close} title={title} size="md">
                <Stack>
                    {filters.length === 0 ? (
                        <Text c="dimmed" ta="center" py="md">
                            {t`No filters available`}
                        </Text>
                    ) : (
                        filters.map(filter => {
                            return (
                                <div key={filter.field}>
                                    {renderFilterInput(filter)}
                                </div>
                            );
                        })
                    )}

                    <Group justify="flex-end" mt="md">
                        <Button
                            variant="light"
                            onClick={handleReset}
                            disabled={!hasActiveFilters}
                        >
                            {t`Reset`}
                        </Button>
                        <Button
                            onClick={handleSave}
                        >
                            {t`Apply`}
                        </Button>
                    </Group>
                </Stack>
            </Modal>
        </>
    );
};
