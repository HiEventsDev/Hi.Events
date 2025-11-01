import {Badge, Stack, Text} from "@mantine/core";
import {t} from "@lingui/macro";
import {AdminAccount} from "../../../api/admin.client";
import {IconCalendar, IconWorld, IconBuildingBank, IconUsers} from "@tabler/icons-react";
import classes from "./AdminAccountsTable.module.scss";

interface AdminAccountsTableProps {
    accounts: AdminAccount[];
}

const AdminAccountsTable = ({accounts}: AdminAccountsTableProps) => {
    if (!accounts || accounts.length === 0) {
        return (
            <div className={classes.emptyState}>
                <Text size="lg" c="dimmed">{t`No accounts found`}</Text>
            </div>
        );
    }

    const formatDate = (dateString?: string) => {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString();
    };

    return (
        <div className={classes.cardsContainer}>
            {accounts.map((account) => (
                <div key={account.id} className={classes.accountCard}>
                    <div className={classes.cardHeader}>
                        <div className={classes.accountInfo}>
                            <h3 className={classes.accountName}>{account.name}</h3>
                            <span className={classes.accountEmail}>{account.email}</span>
                        </div>
                    </div>

                    <div className={classes.cardBody}>
                        <div className={classes.statsGrid}>
                            <div className={classes.statItem}>
                                <IconBuildingBank size={18} />
                                <Stack gap={2}>
                                    <Text size="xs" c="dimmed">{t`Events`}</Text>
                                    <Text size="lg" fw={600}>{account.events_count}</Text>
                                </Stack>
                            </div>
                            <div className={classes.statItem}>
                                <IconUsers size={18} />
                                <Stack gap={2}>
                                    <Text size="xs" c="dimmed">{t`Users`}</Text>
                                    <Text size="lg" fw={600}>{account.users_count}</Text>
                                </Stack>
                            </div>
                        </div>

                        <div className={classes.cardFooter}>
                            <div className={classes.footerInfo}>
                                <div className={classes.footerItem}>
                                    <IconCalendar size={14} />
                                    <span>{formatDate(account.created_at)}</span>
                                </div>
                                {account.timezone && (
                                    <div className={classes.footerItem}>
                                        <IconWorld size={14} />
                                        <span>{account.timezone}</span>
                                    </div>
                                )}
                                {account.currency_code && (
                                    <div className={classes.footerItem}>
                                        <Badge size="sm" variant="light">
                                            {account.currency_code}
                                        </Badge>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            ))}
        </div>
    );
};

export default AdminAccountsTable;
