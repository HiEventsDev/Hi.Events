import {ActionIcon, CopyButton as MantineCopy, rem, Tooltip} from '@mantine/core';
import {IconCheck, IconCopy} from '@tabler/icons-react';
import {t} from "@lingui/macro";

interface CopyButtonProps {
    value: string;
    label?: string;
}

export const CopyButton = ({value, label}: CopyButtonProps) => {
    return (
        <MantineCopy value={value} timeout={2000}>
            {({copied, copy}) => {
                const name = copied ? t`Copied` : (label ?? t`Copy`);

                return (
                    <Tooltip label={name} withArrow position="right" events={{hover: true, focus: true, touch: false}}>
                        <ActionIcon color={copied ? 'teal' : 'gray'} variant="subtle" onClick={copy} aria-label={name}>
                            {copied ? (
                                <IconCheck style={{width: rem(16)}}/>
                            ) : (
                                <IconCopy style={{width: rem(16)}}/>
                            )}
                        </ActionIcon>
                    </Tooltip>
                );
            }}
        </MantineCopy>
    );
}
