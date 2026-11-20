import {Button, Checkbox, Menu} from '@mantine/core';
import {IconColumns} from '@tabler/icons-react';
import {Table, VisibilityState} from '@tanstack/react-table';
import {t} from '@lingui/macro';
import type {TanStackTableColumnMeta} from '../TanStackTable';

interface ColumnVisibilityToggleProps<TData> {
    table: Table<TData>;
    defaultColumnVisibility?: VisibilityState;
}

export function ColumnVisibilityToggle<TData>({table, defaultColumnVisibility = {}}: ColumnVisibilityToggleProps<TData>) {
    const columns = table.getAllLeafColumns().filter((column) => column.getCanHide());

    if (columns.length === 0) {
        return null;
    }

    const isDefault = columns.every(column => column.getIsVisible() === (defaultColumnVisibility[column.id] ?? true));

    return (
        <Menu shadow="md" width={220} closeOnItemClick={false} position="bottom-end">
            <Menu.Target>
                <Button variant="default" leftSection={<IconColumns size={16}/>}>
                    {t`Columns`}
                </Button>
            </Menu.Target>

            <Menu.Dropdown>
                <Menu.Label>{t`Toggle columns`}</Menu.Label>
                {columns.map((column) => {
                    const columnDef = column.columnDef;
                    const label = (columnDef.meta as TanStackTableColumnMeta | undefined)?.label
                        ?? (typeof columnDef.header === 'string' ? columnDef.header : (columnDef.id || column.id));

                    return (
                        <Menu.Item key={column.id} component="label">
                            <Checkbox
                                size="xs"
                                checked={column.getIsVisible()}
                                onChange={column.getToggleVisibilityHandler()}
                                label={label}
                            />
                        </Menu.Item>
                    );
                })}
                {!isDefault && (
                    <>
                        <Menu.Divider/>
                        <Menu.Item onClick={() => table.setColumnVisibility(defaultColumnVisibility)}>
                            {t`Reset`}
                        </Menu.Item>
                    </>
                )}
            </Menu.Dropdown>
        </Menu>
    );
}
