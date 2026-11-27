import {t} from "@lingui/macro";
import {Button, Menu, ScrollArea, Text} from "@mantine/core";
import {IconBraces} from "@tabler/icons-react";
import {EmailTemplateToken} from "../../../../types.ts";
import classes from "./InsertTokenMenu.module.scss";

interface InsertTokenMenuProps {
    tokens: EmailTemplateToken[];
    onInsert: (token: string) => void;
}

export const InsertTokenMenu = ({tokens, onInsert}: InsertTokenMenuProps) => (
    <Menu position="bottom-end" withinPortal classNames={{dropdown: classes.dropdown}}>
        <Menu.Target>
            <Button className={classes.target} variant="subtle" color="gray" size="compact-sm" leftSection={<IconBraces size={14}/>}>
                {t`Insert`}
            </Button>
        </Menu.Target>
        <Menu.Dropdown>
            <Menu.Label>{t`Personalise with`}</Menu.Label>
            <ScrollArea.Autosize mah={360}>
                {tokens.map(token => (
                    <Menu.Item key={token.token} onClick={() => onInsert(token.token)}>
                        <Text size="xs" ff="monospace">{token.token}</Text>
                        <Text size="xs" c="dimmed">{token.description}</Text>
                    </Menu.Item>
                ))}
            </ScrollArea.Autosize>
        </Menu.Dropdown>
    </Menu>
);
