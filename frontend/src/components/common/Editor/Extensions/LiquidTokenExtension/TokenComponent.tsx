import {NodeViewWrapper, ReactNodeViewProps} from '@tiptap/react';
import {Badge, Tooltip} from '@mantine/core';
import {IconCode} from '@tabler/icons-react';

export const TokenComponent = ({node, selected}: ReactNodeViewProps) => {
    const {tokenName, tokenDescription} = node.attrs as { tokenName: string; tokenDescription: string };

    const tokenBadge = (
        <Badge
            size="sm"
            variant={selected ? 'filled' : 'light'}
            color="blue"
            leftSection={<IconCode size={12}/>}
            style={{
                cursor: 'default',
                fontFamily: 'monospace',
                fontSize: '12px',
                whiteSpace: 'nowrap',
                display: 'inline-flex',
                alignItems: 'center',
                verticalAlign: 'baseline',
            }}
        >
            {tokenName}
        </Badge>
    );

    return (
        <NodeViewWrapper 
            as="span" 
            style={{
                display: 'inline-block', 
                margin: '0 2px',
                whiteSpace: 'nowrap',
                verticalAlign: 'baseline',
            }}
        >
            {tokenDescription ? (
                <Tooltip label={tokenDescription} withinPortal>
                    {tokenBadge}
                </Tooltip>
            ) : (
                tokenBadge
            )}
        </NodeViewWrapper>
    );
};
