import {Tooltip} from "@mantine/core";

interface TruncateProps {
    text?: string;
    length?: number;
    showTooltip?: boolean;
}

export const Truncate = ({text = '', length = 35, showTooltip = true}: TruncateProps) => {
    if (text?.length <= length) {
        return <>{text}</>;
    }

    const truncated = text.length > length ? text.substring(0, length) + '...' : text;

    if (!showTooltip) {
        return (
            <span title={text}>{truncated}</span>
        )
    }

    return (
        <Tooltip withArrow label={text}>
            <span>{truncated}</span>
        </Tooltip>
    );
};

export default Truncate;