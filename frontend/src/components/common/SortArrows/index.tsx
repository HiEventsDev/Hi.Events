import {ActionIcon} from '@mantine/core';
import {IconCaretDownFilled, IconCaretUpFilled} from '@tabler/icons-react';

interface SortArrowsProps {
    upArrowEnabled: boolean;
    downArrowEnabled: boolean;
    onSortUp: () => void;
    onSortDown: () => void;
    flexDirection?: 'row' | 'column';
}

export const SortArrows = ({
                               upArrowEnabled,
                               downArrowEnabled,
                               onSortUp,
                               onSortDown,
                               flexDirection = 'row'
                           }: SortArrowsProps) => {
    return (
        <div style={{
            display: 'flex',
            flexDirection: flexDirection,
            alignItems: 'center'
        }}>
            <ActionIcon
                onClick={upArrowEnabled ? onSortUp : undefined}
                variant="transparent"
                aria-label="Sort Up"
                color={upArrowEnabled ? 'currentColor' : '#ddd'}
            >
                <IconCaretUpFilled size={18}/>
            </ActionIcon>
            <ActionIcon
                onClick={downArrowEnabled ? onSortDown : undefined}
                variant="transparent"
                aria-label="Sort Down"
                color={downArrowEnabled ? 'currentColor' : '#ddd'}
            >
                <IconCaretDownFilled size={18}/>
            </ActionIcon>
        </div>
    );
};
