import {useState} from 'react';
import {ActionIcon, Badge, Divider, Group, Menu, ScrollArea, Text, Tooltip} from '@mantine/core';
import {IconBraces, IconChevronDown} from '@tabler/icons-react';
import {Editor} from '@tiptap/react';
import {EmailTemplateToken, EmailTemplateType} from '../../../../types';
import {useGetEmailTemplateTokens} from '../../../../queries/useGetEmailTemplateTokens';
import {t, Trans} from '@lingui/macro';

interface LiquidTokenControlProps {
    editor: Editor;
    templateType: EmailTemplateType;
}

export const LiquidTokenControl = ({editor, templateType}: LiquidTokenControlProps) => {
    const [opened, setOpened] = useState(false);
    const {data: tokensData, isLoading} = useGetEmailTemplateTokens(templateType);

    const insertToken = (token: EmailTemplateToken) => {
        if (editor) {
            // Check if it's a conditional token (starts with {% if)
            const isConditional = token.token.startsWith('{% if');

            if (isConditional) {
                // Insert conditional block with cursor positioning
                const conditionalBlock = `${token.token}\n  \n{% endif %}`;
                editor.chain().focus().insertContent(conditionalBlock).run();
            } else {
                // Insert regular token as plain text
                editor.chain().focus().insertContent(token.token).run();
            }
        }
        setOpened(false);
    };

    const insertTokenAsText = (tokenText: string) => {
        if (editor) {
            editor.chain().focus().insertContent(tokenText).run();
        }
        setOpened(false);
    };

    if (isLoading || !tokensData?.tokens) {
        return (
            <Tooltip label={t`Loading tokens...`}>
                <ActionIcon variant="subtle" color="gray" loading>
                    <IconBraces size={16}/>
                </ActionIcon>
            </Tooltip>
        );
    }

    const tokens = tokensData.tokens;
    const regularTokens = tokens.filter(token => !token.token.startsWith('{% if'));
    const conditionalTokens = tokens.filter(token => token.token.startsWith('{% if'));

    return (
        <Menu
            opened={opened}
            onChange={setOpened}
            position="bottom-start"
            withinPortal
        >
            <Menu.Target>
                <Tooltip label={t`Insert Liquid Token`}>
                    <ActionIcon variant="subtle" color="blue">
                        <Group gap={2}>
                            <IconBraces size={16}/>
                            <IconChevronDown size={12}/>
                        </Group>
                    </ActionIcon>
                </Tooltip>
            </Menu.Target>

            <Menu.Dropdown style={{maxWidth: '350px'}}>
                <Menu.Label>
                    <Trans>Available Tokens</Trans>
                </Menu.Label>

                <ScrollArea.Autosize mah={200}>
                    {regularTokens.map((token, index) => (
                        <Menu.Item
                            key={index}
                            onClick={() => insertToken(token)}
                            style={{padding: '8px 12px'}}
                        >
                            <div>
                                <Badge
                                    size="xs"
                                    variant="light"
                                    color="blue"
                                    style={{fontFamily: 'monospace', marginBottom: '4px'}}
                                >
                                    {token.token}
                                </Badge>
                                <Text size="xs" c="dimmed" style={{lineHeight: 1.3}}>
                                    {token.description}
                                </Text>
                                <Text size="xs" c="dimmed" fs="italic" style={{marginTop: '2px'}}>
                                    {token.example}
                                </Text>
                            </div>
                        </Menu.Item>
                    ))}
                </ScrollArea.Autosize>

                {conditionalTokens.length > 0 && (
                    <>
                        <Divider/>
                        <Menu.Label>
                            <Trans>Conditional Blocks</Trans>
                        </Menu.Label>

                        <ScrollArea.Autosize mah={150}>
                            {conditionalTokens.map((token, index) => (
                                <Menu.Item
                                    key={index}
                                    onClick={() => insertTokenAsText(token.token + '\n  \n{% endif %}')}
                                    style={{padding: '8px 12px'}}
                                >
                                    <div>
                                        <Badge
                                            size="xs"
                                            variant="light"
                                            color="orange"
                                            style={{fontFamily: 'monospace', marginBottom: '4px'}}
                                        >
                                            {token.token.split(' ')[2] + ' block'}
                                        </Badge>
                                        <Text size="xs" c="dimmed" style={{lineHeight: 1.3}}>
                                            {token.description}
                                        </Text>
                                        <Text size="xs" c="dimmed" fs="italic" style={{marginTop: '2px'}}>
                                            {token.example}
                                        </Text>
                                    </div>
                                </Menu.Item>
                            ))}
                        </ScrollArea.Autosize>
                    </>
                )}
            </Menu.Dropdown>
        </Menu>
    );
};
