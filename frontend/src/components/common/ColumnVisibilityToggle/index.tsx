import {Button, Checkbox, Menu} from '@mantine/core';
import {IconColumns} from '@tabler/icons-react';
import {Table} from '@tanstack/react-table';
import {t} from '@lingui/macro';

interface ColumnVisibilityToggleProps<TData> {
    table: Table<TData>;
}

export function ColumnVisibilityToggle<TData>({table}: ColumnVisibilityToggleProps<TData>) {
    const columns = table.getAllLeafColumns().filter((column) => column.getCanHide());

    if (columns.length === 0) {
        return null;
    }

    return (
        <Menu shadow="md" width={200} closeOnItemClick={false}>
            <Menu.Target>
                <Button size="xs" variant="light" leftSection={<IconColumns size={16}/>}>
                    {t`Columns`}
                </Button>
            </Menu.Target>

            <Menu.Dropdown>
                <Menu.Label>{t`Toggle columns`}</Menu.Label>
                {columns.map((column) => {
                    const columnDef = column.columnDef;
                    const label = typeof columnDef.header === 'string'
                        ? columnDef.header
                        : (columnDef.id || column.id);

                    return (
                        <Menu.Item key={column.id}>
                            <Checkbox
                                checked={column.getIsVisible()}
                                onChange={column.getToggleVisibilityHandler()}
                                label={label}
                            />
                        </Menu.Item>
                    );
                })}
            </Menu.Dropdown>
        </Menu>
    );
}
