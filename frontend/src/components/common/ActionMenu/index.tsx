import React from 'react';
import {Button, Group, Menu} from '@mantine/core';
import {IconDotsVertical} from '@tabler/icons-react';
import {useAccess} from "../../../hooks/useAccess.ts";

export interface MenuItem {
    label: string;
    icon: React.ReactNode;
    onClick: () => void;
    color?: string;
    visible?: boolean;
    requiresEdit?: boolean;
    dataTestId?: string;
}

export interface ActionMenuItemsGroup {
    label: string;
    items: MenuItem[];
    showDividerAbove?: boolean;
}

interface ActionMenuProps {
    itemsGroups: ActionMenuItemsGroup[];
    target?: React.ReactNode;
    dataTestId?: string;
}

const DefaultTarget = () => (
    <Button variant="transparent">
        <IconDotsVertical/>
    </Button>
);

export const ActionMenu: React.FC<ActionMenuProps> = ({
                                                          itemsGroups,
                                                          target = <DefaultTarget/>,
                                                          dataTestId
                                                      }) => {
    const {isReadOnly} = useAccess();
    const visibleGroups = itemsGroups
        .map(group => ({
            ...group,
            items: group.items.filter(item => item.visible !== false && !(item.requiresEdit && isReadOnly)),
        }))
        .filter(group => group.items.length > 0);

    if (visibleGroups.length === 0) {
        return null;
    }

    return (
        <>
            <Menu shadow="md" width={200}>
                <Menu.Target>
                    <div style={{cursor: 'pointer'}} data-testid={dataTestId}>
                        {target}
                    </div>
                </Menu.Target>

                <Menu.Dropdown>
                    {visibleGroups.map((group, groupIndex) => (
                        <React.Fragment key={groupIndex}>
                            {groupIndex > 0 && group.showDividerAbove && <Menu.Divider/>}
                            <Menu.Label>{group.label}</Menu.Label>
                            {group.items.map((item, itemIndex) => (
                                <Menu.Item
                                    key={itemIndex}
                                    color={item.color}
                                    leftSection={item.icon}
                                    onClick={item.onClick}
                                    data-testid={item.dataTestId}
                                >
                                    {item.label}
                                </Menu.Item>
                            ))}
                        </React.Fragment>
                    ))}
                </Menu.Dropdown>
            </Menu>
        </>
    );
};
