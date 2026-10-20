import {Button, Select, Skeleton, TextInput} from "@mantine/core";
import {useDisclosure} from "@mantine/hooks";
import {t} from "@lingui/macro";
import {IconSearch, IconUserPlus} from "@tabler/icons-react";
import {useMemo, useState} from "react";
import {useGetUsers} from "../../../../queries/useGetUsers.ts";
import {useGetMe} from "../../../../queries/useGetMe.ts";
import {useGetOrganizers} from "../../../../queries/useGetOrganizers.ts";
import {useResendUserInvitation} from "../../../../mutations/useResendUserInvitation.ts";
import {useDeleteUserInvitation} from "../../../../mutations/useDeleteUserInvitation.ts";
import {useResetAccountUserTwoFactor} from "../../../../mutations/useResetAccountUserTwoFactor.ts";
import {useEditUser} from "../../../../mutations/useEditUser.ts";
import {useAccess} from "../../../../hooks/useAccess.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {confirmationDialog} from "../../../../utilites/confirmationDialog.tsx";
import {IdParam, User, UserRole} from "../../../../types.ts";
import {InviteUserModal} from "../../../modals/InviteUserModal";
import {EditUserModal} from "../../../modals/EditUserModal";
import {TwoFactorPolicy} from "./TwoFactorPolicy.tsx";
import {MemberRow} from "./MemberRow.tsx";
import {SettingsPage} from "../../../common/SettingsPage";
import classes from "./Team.module.scss";

const errorMessage = (error: any) => error?.response?.data?.message ?? t`Something went wrong! Please try again`;

const Team = () => {
    const {can} = useAccess();
    const canManageTeam = can('MANAGE_TEAM');
    const usersQuery = useGetUsers(canManageTeam);
    const organizersQuery = useGetOrganizers();
    const {data: me} = useGetMe();
    const resendInvitationMutation = useResendUserInvitation();
    const revokeInvitationMutation = useDeleteUserInvitation();
    const resetTwoFactorMutation = useResetAccountUserTwoFactor();
    const editMutation = useEditUser();
    const [inviteOpen, {open: openInvite, close: closeInvite}] = useDisclosure(false);
    const [selectedUser, setSelectedUser] = useState<User | null>(null);
    const [search, setSearch] = useState('');
    const [roleFilter, setRoleFilter] = useState<string | null>(null);
    const [signInFilter, setSignInFilter] = useState<string | null>(null);

    const users = usersQuery.data?.data;
    const organizers = organizersQuery.data?.data ?? [];

    const {members, invitations, stats} = useMemo(() => {
        const all = users ?? [];
        const query = search.trim().toLowerCase();
        const matches = (user: User) =>
            (!query || `${user.first_name} ${user.last_name ?? ''} ${user.email}`.toLowerCase().includes(query))
            && (!roleFilter || user.role === roleFilter || (roleFilter === 'ADMIN' && user.role === 'SUPERADMIN'))
            && (!signInFilter || user.sso_sign_in === signInFilter);
        const joined = all.filter(user => user.status !== 'INVITED');
        const active = joined.filter(user => user.status === 'ACTIVE');

        const rank = (user: User) => (user.id === me?.id ? 0 : user.is_account_owner ? 1 : user.role === 'ADMIN' || user.role === 'SUPERADMIN' ? 2 : 3);
        const byRank = (a: User, b: User) => rank(a) - rank(b) || `${a.first_name} ${a.last_name}`.localeCompare(`${b.first_name} ${b.last_name}`);

        return {
            members: joined.filter(matches).sort(byRank),
            invitations: all.filter(user => user.status === 'INVITED').filter(matches),
            stats: {
                active: active.length,
                invited: all.length - joined.length,
                admins: active.filter(user => user.role === 'ADMIN' || user.role === 'SUPERADMIN').length,
                twoFactor: active.filter(user => user.two_factor_enabled).length,
            },
        };
    }, [users, search, roleFilter, signInFilter, me?.id]);

    const ssoRequired = (users ?? []).some(user => !!user.sso_sign_in);

    if (!canManageTeam) {
        return null;
    }

    const canResetTwoFactor = (user: User) => !!user.two_factor_enabled
        && user.id !== me?.id
        && (!user.is_account_owner || !!me?.is_account_owner);

    const handleResetTwoFactor = (user: User) => confirmationDialog(
        t`Reset two-factor authentication for ${user.email}? Only do this once you've confirmed it's really them. They'll be able to sign in with just their password and will be emailed about the change.`,
        () => resetTwoFactorMutation.mutate(user.id as IdParam, {
            onSuccess: () => showSuccess(t`Two-factor authentication reset`),
            onError: (error: any) => showError(errorMessage(error)),
        }),
        {confirm: t`Reset two-factor`},
    );

    const handleToggleActive = (user: User) => {
        const deactivating = user.status === 'ACTIVE';
        const save = () => editMutation.mutate({
            userId: user.id,
            userData: {
                first_name: user.first_name,
                last_name: user.last_name,
                role: user.role ?? 'ORGANIZER',
                status: deactivating ? 'INACTIVE' : 'ACTIVE',
            },
        }, {
            onSuccess: () => showSuccess(deactivating ? t`Member deactivated` : t`Member reactivated`),
            onError: (error: any) => showError(errorMessage(error)),
        });

        if (deactivating) {
            confirmationDialog(t`Deactivate ${user.email}? They won't be able to sign in until you reactivate them.`, save, {confirm: t`Deactivate`, destructive: true});
            return;
        }

        save();
    };

    const handleResendInvitation = (user: User) => resendInvitationMutation.mutate({userId: user.id}, {
        onSuccess: () => showSuccess(t`Invitation resent!`),
        onError: (error: any) => showError(errorMessage(error)),
    });

    const handleRevokeInvitation = (user: User) => confirmationDialog(
        t`Are you sure you want to revoke this invitation?`,
        () => revokeInvitationMutation.mutate({userId: user.id}, {
            onSuccess: () => showSuccess(t`Invitation revoked!`),
            onError: (error: any) => showError(errorMessage(error)),
        }),
    );

    const renderRows = (list: User[]) => list.map(user => (
        <MemberRow
            key={user.id}
            user={user}
            isMe={user.id === me?.id}
            organizers={organizers}
            canResetTwoFactor={canResetTwoFactor(user)}
            onEdit={() => setSelectedUser(user)}
            onResetTwoFactor={() => handleResetTwoFactor(user)}
            onToggleActive={() => handleToggleActive(user)}
            onResendInvitation={() => handleResendInvitation(user)}
            onRevokeInvitation={() => handleRevokeInvitation(user)}
        />
    ));

    const roleOptions: { value: UserRole, label: string }[] = [
        {value: 'ADMIN', label: t`Admins`},
        {value: 'ORGANIZER', label: t`Organizers`},
        {value: 'VIEWER', label: t`Viewers`},
    ];

    const isFiltering = !!search.trim() || !!roleFilter || !!signInFilter;

    return (
        <SettingsPage
            width="wide"
            title={t`Team`}
            description={t`Invite people, choose what they can see and do, and keep sign-in secure.`}
            actions={(
                <Button leftSection={<IconUserPlus size={16}/>} onClick={openInvite} data-testid="team-invite-button">
                    {t`Invite member`}
                </Button>
            )}
        >
            <div className={classes.stack}>
                <div className={classes.stats}>
                    {[
                        {label: t`Active members`, value: stats.active},
                        {label: t`Pending invites`, value: stats.invited},
                        {label: t`Admins`, value: stats.admins},
                        {label: t`Two-factor on`, value: `${stats.twoFactor}/${stats.active}`},
                    ].map(stat => (
                        <div className={classes.stat} key={stat.label}>
                            <span className={classes.statLabel}>{stat.label}</span>
                            {users ? <span className={classes.statValue}>{stat.value}</span> : <Skeleton height={24} width={40}/>}
                        </div>
                    ))}
                </div>

                {users && <TwoFactorPolicy users={users}/>}

                <section className={classes.panel}>
                    <div className={classes.toolbar}>
                        <TextInput
                            className={classes.search}
                            leftSection={<IconSearch size={16}/>}
                            placeholder={t`Search by name or email`}
                            value={search}
                            onChange={(event) => setSearch(event.currentTarget.value)}
                            aria-label={t`Search members`}
                        />
                        <Select
                            className={classes.roleFilter}
                            placeholder={t`All roles`}
                            data={roleOptions}
                            value={roleFilter}
                            onChange={setRoleFilter}
                            clearable
                            aria-label={t`Filter by role`}
                        />
                        {ssoRequired && (
                            <Select
                                className={classes.roleFilter}
                                placeholder={t`All sign-in methods`}
                                data={[
                                    {value: 'SSO', label: t`Single sign-on`},
                                    {value: 'PASSWORD', label: t`Password`},
                                    {value: 'EXEMPT', label: t`Exempt from SSO`},
                                ]}
                                value={signInFilter}
                                onChange={setSignInFilter}
                                clearable
                                aria-label={t`Filter by sign-in method`}
                            />
                        )}
                    </div>

                    <div className={classes.columns} aria-hidden>
                        <span>{t`Member`}</span>
                        <span>{t`Role`}</span>
                        <span>{t`Access`}</span>
                        <span>{t`Two-factor`}</span>
                        <span>{t`Last active`}</span>
                        <span/>
                    </div>

                    {!users && [0, 1, 2].map(index => (
                        <div className={classes.row} key={index}><Skeleton height={36} radius="md"/></div>
                    ))}

                    {users && renderRows(members)}

                    {users && members.length === 0 && (
                        <div className={classes.empty}>
                            {isFiltering ? t`No members match your search.` : t`No members yet.`}
                        </div>
                    )}

                    {invitations.length > 0 && (
                        <>
                            <div className={classes.groupLabel}>
                                {t`Pending invitations`} <span className={classes.groupCount}>{invitations.length}</span>
                            </div>
                            {renderRows(invitations)}
                        </>
                    )}
                </section>
            </div>

            {inviteOpen && <InviteUserModal onClose={closeInvite}/>}
            {selectedUser && <EditUserModal user={selectedUser} onClose={() => setSelectedUser(null)}/>}
        </SettingsPage>
    );
};

export default Team;
