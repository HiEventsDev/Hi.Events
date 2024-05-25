import {Tooltip as MantineTooltip, TooltipProps} from '@mantine/core';

export const Tooltip = (props: TooltipProps) => {
    return (
        <MantineTooltip
            {...props}
            events={{hover: true, focus: true, touch: true}}
        >
            {props.children}
        </MantineTooltip>
    );
}