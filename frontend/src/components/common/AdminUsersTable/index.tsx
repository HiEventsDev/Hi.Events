import {Badge, Button, Menu, Stack, Text} from "@mantine/core";
import {t} from "@lingui/macro";
import {AdminUser} from "../../../api/admin.client";
import {IconChevronDown, IconCalendar, IconWorld} from "@tabler/icons-react";
import classes from "./AdminUsersTable.module.scss";
import {IdParam} from "../../../types";

interface AdminUsersTableProps {
    users: AdminUser[];
    onImpersonate: (userId: IdParam, accountId: IdParam) => void;
    isLoading?: boolean;
}

const AdminUsersTable = ({users, onImpersonate, isLoading}: AdminUsersTableProps) => {
    if (!users || users.length === 0) {
        return (
            <div className={classes.emptyState}>
                <Text size="lg" c="dimmed">{t`No users found`}</Text>
            </div>
        );
    }

    const getRoleBadgeColor = (role: string) => {
        switch (role) {
            case 'ADMIN':
                return 'blue';
            case 'ORGANIZER':
                return 'green';
            case 'SUPERADMIN':
                return 'red';
            default:
                return 'gray';
        }
    };

    const formatDate = (dateString?: string) => {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString();
    };

    const handleImpersonate = (user: AdminUser) => {
        if (!user.accounts || user.accounts.length === 0) {
            return;
        }

        if (user.accounts.length === 1) {
            onImpersonate(user.id, user.accounts[0].id);
        }
    };

    const isSuperAdmin = (user: AdminUser) => {
        return user.accounts?.some(account => account.role === 'SUPERADMIN');
    };

    const canImpersonateAccount = (role: string) => {
        return role !== 'SUPERADMIN';
    };

    return (
        <div className={classes.cardsContainer}>
            {users.map((user) => {
                const userIsSuperAdmin = isSuperAdmin(user);
                const impersonatableAccounts = user.accounts?.filter(account => canImpersonateAccount(account.role)) || [];

                return (
                    <div key={user.id} className={classes.userCard}>
                        <div className={classes.cardHeader}>
                            <div className={classes.userInfo}>
                                <h3 className={classes.userName}>{user.full_name}</h3>
                                <span className={classes.userEmail}>{user.email}</span>
                            </div>
                            <div className={classes.cardActions}>
                                {!userIsSuperAdmin && impersonatableAccounts.length > 0 && (
                                    <>
                                        {impersonatableAccounts.length === 1 ? (
                                            <Button
                                                size="xs"
                                                variant="light"
                                                onClick={() => handleImpersonate(user)}
                                                disabled={isLoading}
                                                className={classes.impersonateBtn}
                                            >
                                                {t`Impersonate`}
                                            </Button>
                                        ) : (
                                            <Menu shadow="md" width={200}>
                                                <Menu.Target>
                                                    <Button
                                                        size="xs"
                                                        variant="light"
                                                        rightSection={<IconChevronDown size={14} />}
                                                        disabled={isLoading}
                                                        className={classes.impersonateBtn}
                                                    >
                                                        {t`Impersonate`}
                                                    </Button>
                                                </Menu.Target>
                                                <Menu.Dropdown>
                                                    <Menu.Label>{t`Select Account`}</Menu.Label>
                                                    {impersonatableAccounts.map((account) => (
                                                        <Menu.Item
                                                            key={account.id}
                                                            onClick={() => onImpersonate(user.id, account.id)}
                                                        >
                                                            <Stack gap={4}>
                                                                <Text size="sm">{account.name}</Text>
                                                                <Badge
                                                                    size="xs"
                                                                    color={getRoleBadgeColor(account.role)}
                                                                >
                                                                    {account.role}
                                                                </Badge>
                                                            </Stack>
                                                        </Menu.Item>
                                                    ))}
                                                </Menu.Dropdown>
                                            </Menu>
                                        )}
                                    </>
                                )}
                            </div>
                        </div>

                    <div className={classes.cardBody}>
                        <div className={classes.section}>
                            <span className={classes.sectionLabel}>{t`Accounts`}</span>
                            {user.accounts && user.accounts.length > 0 ? (
                                <div className={classes.accountsList}>
                                    {user.accounts.map((account) => (
                                        <div key={account.id} className={classes.accountItem}>
                                            <span className={classes.accountName}>{account.name}</span>
                                            <Badge
                                                size="sm"
                                                color={getRoleBadgeColor(account.role)}
                                            >
                                                {account.role}
                                            </Badge>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <Text size="sm" c="dimmed">{t`No accounts`}</Text>
                            )}
                        </div>

                        <div className={classes.cardFooter}>
                            <div className={classes.footerInfo}>
                                <div className={classes.footerItem}>
                                    <IconCalendar size={14} />
                                    <span>{formatDate(user.created_at)}</span>
                                </div>
                                {user.timezone && (
                                    <div className={classes.footerItem}>
                                        <IconWorld size={14} />
                                        <span>{user.timezone}</span>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
                );
            })}
        </div>
    );
};

export default AdminUsersTable;
