import React from 'react';
import {Button, Group, Menu} from '@mantine/core';
import {IconDotsVertical} from '@tabler/icons-react';

export interface MenuItem {
    label: string;
    icon: React.ReactNode;
    onClick: () => void;
    color?: string;
    visible?: boolean;
}

export interface ActionMenuItemsGroup {
    label: string;
    items: MenuItem[];
    showDividerAbove?: boolean;
}

interface ActionMenuProps {
    itemsGroups: ActionMenuItemsGroup[];
    target?: React.ReactNode;
}

export const ActionMenu: React.FC<ActionMenuProps> = ({
                                                          itemsGroups,
                                                          target = <IconDotsVertical/>
                                                      }) => {
    return (
        <Group wrap={'nowrap'} gap={0} justify={'flex-end'}>
            <Menu shadow="md" width={200}>
                <Menu.Target>
                    <Button variant={'transparent'}>
                        {target}
                    </Button>
                </Menu.Target>

                <Menu.Dropdown>
                    {itemsGroups.map((group, groupIndex) => (
                        <React.Fragment key={groupIndex}>
                            {group.showDividerAbove && <Menu.Divider/>}
                            <Menu.Label>{group.label}</Menu.Label>
                            {group.items.map((item, itemIndex) => item.visible !== false && (
                                <Menu.Item
                                    key={itemIndex}
                                    color={item.color}
                                    leftSection={item.icon}
                                    onClick={item.onClick}
                                >
                                    {item.label}
                                </Menu.Item>
                            ))}

                        </React.Fragment>
                    ))}
                </Menu.Dropdown>
            </Menu>
        </Group>
    );
};
