import {notifications} from '@mantine/notifications';
import {IconCheck, IconX} from '@tabler/icons-react';

interface NotificationMessages {
    loading: {
        title: string;
        message: string;
    };
    success: {
        title: string;
        message: string;
    };
    error: {
        title: string;
        message: string;
    };
}

export const withLoadingNotification = async <T, >(
    asyncOperation: () => Promise<T>,
    messages: NotificationMessages
): Promise<T> => {
    const notificationId = notifications.show({
        loading: true,
        title: messages.loading.title,
        message: messages.loading.message,
        autoClose: false,
        withCloseButton: false,
    });

    try {
        const result = await asyncOperation();

        notifications.update({
            id: notificationId,
            title: messages.success.title,
            message: messages.success.message,
            color: 'green',
            icon: <IconCheck size="1rem"/>,
            autoClose: 2000,
            loading: false,
        });

        return result;
    } catch (error) {
        notifications.update({
            id: notificationId,
            title: messages.error.title,
            message: messages.error.message,
            color: 'red',
            icon: <IconX size="1rem"/>,
            autoClose: 2000,
            loading: false,
        });
        throw error;
    }
};
