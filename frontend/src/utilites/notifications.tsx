import {notifications} from "@mantine/notifications";
import {IconCheck, IconInfoSmall, IconX} from "@tabler/icons-react";
import {ReactNode} from "react";

export const showSuccess = (message: ReactNode) => {
    notifications.show({
        message: message,
        color: 'green',
        icon: <IconCheck/>
    })
}

export const showInfo = (message: ReactNode) => {
    notifications.show({
        message: message,
        color: 'blue',
        icon: <IconInfoSmall/>
    })
}

export const showError = (message: string) => {
    notifications.show({
        message: message,
        color: 'red',
        icon: <IconX/>
    })
}