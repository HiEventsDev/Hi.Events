import {notifications} from "@mantine/notifications";
import {IconCheck, IconInfoSmall, IconX} from "@tabler/icons-react";
import React, {ReactNode} from "react";

export const showSuccess = (message: ReactNode, icon: ReactNode = <IconCheck/>) => {
    notifications.show({
        message: message,
        color: 'green',
        icon: icon,
        position: 'top-center',
    })
}

export const showInfo = (message: ReactNode, icon: ReactNode = <IconInfoSmall/>) => {
    notifications.show({
        message: message,
        color: 'blue',
        icon: icon,
        position: 'top-center',
    })
}

export const showError = (message: React.ReactNode, icon: ReactNode = <IconX/>) => {
    notifications.show({
        message: message,
        color: 'red',
        icon: icon,
        position: 'top-center',
    })
}

/**
 * Success toast with an inline "Undo" action. Matches the same position and palette as
 * showSuccess so the two look at home next to each other. The undo handler runs only if
 * the user clicks before the toast auto-closes.
 */
export const showSuccessWithUndo = (
    message: ReactNode,
    onUndo: () => void,
    options: { undoLabel?: string; autoClose?: number } = {},
) => {
    const {undoLabel = 'Undo', autoClose = 5000} = options;
    const id = `undo-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`;

    const handleUndo = () => {
        notifications.hide(id);
        onUndo();
    };

    notifications.show({
        id,
        color: 'green',
        icon: <IconCheck/>,
        position: 'top-center',
        autoClose,
        message: (
            <span style={{display: 'flex', alignItems: 'center', gap: 12, justifyContent: 'space-between'}}>
                <span style={{flex: 1}}>{message}</span>
                <button
                    type="button"
                    onClick={handleUndo}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            handleUndo();
                        }
                    }}
                    style={{
                        background: 'transparent',
                        border: 'none',
                        padding: '2px 8px',
                        color: 'var(--mantine-color-green-8)',
                        fontWeight: 700,
                        cursor: 'pointer',
                        textTransform: 'uppercase',
                        fontSize: 12,
                        letterSpacing: '0.05em',
                        flexShrink: 0,
                    }}
                >
                    {undoLabel}
                </button>
            </span>
        ),
    });
};
