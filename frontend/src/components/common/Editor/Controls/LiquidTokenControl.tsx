import {Badge, Menu, ScrollArea, Text} from '@mantine/core';
import {IconBraces} from '@tabler/icons-react';
import {RichTextEditor, useRichTextEditorContext} from '@mantine/tiptap';
import {EmailTemplateToken, EmailTemplateType} from '../../../../types';
import {useGetEmailTemplateTokens} from '../../../../queries/useGetEmailTemplateTokens';
import {t, Trans} from '@lingui/macro';

interface LiquidTokenControlProps {
    templateType: EmailTemplateType;
}

export const LiquidTokenControl = ({templateType}: LiquidTokenControlProps) => {
    const {editor} = useRichTextEditorContext();
    const {data: tokensData, isLoading} = useGetEmailTemplateTokens(templateType);

    const insertToken = (token: EmailTemplateToken) => {
        if (editor) {
            editor.chain().focus().insertContent(token.token).run();
        }
    };

    if (isLoading || !tokensData?.tokens) {
        return (
            <RichTextEditor.Control
                title={t`Loading tokens...`}
                aria-label={t`Loading tokens...`}
                disabled
            >
                <IconBraces size={16}/>
            </RichTextEditor.Control>
        );
    }

    const tokens = tokensData.tokens.filter(token => !token.token.startsWith('{% if'));

    return (
        <Menu shadow="md" width={380} position="bottom-start" withinPortal>
            <Menu.Target>
                <RichTextEditor.Control
                    title={t`Insert Liquid Token`}
                    aria-label={t`Insert Liquid Token`}
                >
                    <IconBraces size={16}/>
                </RichTextEditor.Control>
            </Menu.Target>

            <Menu.Dropdown>
                <Menu.Label>
                    <Trans>Available Tokens</Trans>
                </Menu.Label>

                <ScrollArea h={400}>
                    {tokens.map((token, index) => (
                        <Menu.Item
                            key={index}
                            onClick={() => insertToken(token)}
                            p="sm"
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
                </ScrollArea>
            </Menu.Dropdown>
        </Menu>
    );
};
