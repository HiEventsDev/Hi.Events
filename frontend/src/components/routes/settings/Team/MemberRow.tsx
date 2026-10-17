import {ActionIcon, Avatar, Badge, Button, Menu, Tooltip} from "@mantine/core";
import {t} from "@lingui/macro";
import {
    IconDotsVertical,
    IconLock,
    IconPencil,
    IconPlayerPause,
    IconPlayerPlay,
    IconSend,
    IconShieldCheck,
    IconShieldOff,
    IconTrash,
    IconWorld,
} from "@tabler/icons-react";
import {Organizer, User} from "../../../../types.ts";
import {getInitials} from "../../../../utilites/helpers.ts";
import {relativeDate} from "../../../../utilites/dates.ts";
import {roleLabel} from "../../../common/TeamAccessFields";
import classes from "./Team.module.scss";

const statusLabel = (status: User['status']) => {
    switch (status) {
        case 'INVITED':
            return t`Invited`;
        case 'INACTIVE':
            return t`Inactive`;
        default:
            return t`Active`;
    }
};

const SignInBadge = ({method}: { method: NonNullable<User['sso_sign_in']> }) => {
    switch (method) {
        case 'SSO':
            return <Badge size="xs" variant="light" color="blue">{t`SSO`}</Badge>;
        case 'EXEMPT':
            return <Badge size="xs" variant="outline" color="gray">{t`Exempt`}</Badge>;
        default:
            return <Badge size="xs" variant="light" color="gray">{t`Password`}</Badge>;
    }
};

const accessSummary = (user: User) => {
    if (user.access?.scope !== 'RESTRICTED') {
        return t`Whole account`;
    }

    const organizerCount = user.access.organizer_ids.length;
    const eventCount = user.access.event_ids.length;
    const parts = [
        organizerCount === 1 ? t`1 organizer` : organizerCount > 1 ? t`${organizerCount} organizers` : null,
        eventCount === 1 ? t`1 event` : eventCount > 1 ? t`${eventCount} events` : null,
    ].filter(Boolean);

    return parts.length ? parts.join(' · ') : t`No access yet`;
};

const permissionNote = (user: User) => {
    const permissions = user.access?.permissions ?? [];

    if (user.role === 'ORGANIZER' && !permissions.includes('VIEW_FINANCIALS')) {
        return t`No financials`;
    }

    if (user.role === 'ORGANIZER' && !permissions.includes('ISSUE_REFUNDS')) {
        return t`No refunds`;
    }

    if (user.role === 'VIEWER' && permissions.includes('VIEW_FINANCIALS')) {
        return t`Can see financials`;
    }

    return null;
};

interface MemberRowProps {
    user: User;
    isMe: boolean;
    organizers: Organizer[];
    canResetTwoFactor: boolean;
    onEdit: () => void;
    onResetTwoFactor: () => void;
    onToggleActive: () => void;
    onResendInvitation: () => void;
    onRevokeInvitation: () => void;
}

export const MemberRow = ({
                              user,
                              isMe,
                              organizers,
                              canResetTwoFactor,
                              onEdit,
                              onResetTwoFactor,
                              onToggleActive,
                              onResendInvitation,
                              onRevokeInvitation,
                          }: MemberRowProps) => {
    const isInvited = user.status === 'INVITED';
    const isInactive = user.status === 'INACTIVE';
    const isRestricted = user.access?.scope === 'RESTRICTED';
    const canToggleActive = !isInvited && !isMe && !user.is_account_owner && user.role !== 'SUPERADMIN';
    const note = permissionNote(user);
    const grantedOrganizers = organizers
        .filter(organizer => user.access?.organizer_ids.includes(Number(organizer.id)))
        .map(organizer => organizer.name);

    return (
        <div
            className={`${classes.row} ${isInactive ? classes.rowInactive : ''}`}
            data-testid="team-member-row"
            onClick={onEdit}
        >
            <div className={classes.identity}>
                <Avatar size={36} radius="xl" color="primary">
                    {getInitials(`${user.first_name} ${user.last_name ?? ''}`)}
                </Avatar>
                <div className={classes.identityText}>
                    <div className={classes.nameLine}>
                        <span className={classes.name}>{user.first_name} {user.last_name}</span>
                        {isMe && <Badge size="xs" variant="light" color="gray">{t`You`}</Badge>}
                        {(isInvited || isInactive) && (
                            <Badge size="xs" variant="light" color={isInvited ? 'orange' : 'red'}>
                                {statusLabel(user.status)}
                            </Badge>
                        )}
                        {user.sso_sign_in && <SignInBadge method={user.sso_sign_in}/>}
                    </div>
                    <span className={classes.email}>{user.email}</span>
                </div>
            </div>

            <div className={classes.meta}>
                <div className={classes.cellRole}>
                    <span className={classes.role}>{user.is_account_owner ? t`Owner` : roleLabel(user.role)}</span>
                </div>

                <div className={classes.cellAccess}>
                    <Tooltip
                        label={grantedOrganizers.join(', ')}
                        disabled={!grantedOrganizers.length}
                        withArrow
                    >
                        <span className={classes.access}>
                            {isRestricted ? <IconLock size={14}/> : <IconWorld size={14}/>}
                            {accessSummary(user)}
                        </span>
                    </Tooltip>
                    {note && <span className={classes.note}>{note}</span>}
                </div>

                <div className={classes.cellTwoFactor}>
                    {user.two_factor_enabled ? (
                        <span className={classes.twoFactorOn}><IconShieldCheck size={14}/>{t`On`}</span>
                    ) : (
                        <span className={classes.twoFactorOff}><IconShieldOff size={14}/>{t`Off`}</span>
                    )}
                </div>

                <div className={classes.cellLastSeen}>
                    {isInvited ? t`Not joined yet` : user.last_login_at ? relativeDate(user.last_login_at) : t`Never`}
                </div>
            </div>

            <div className={classes.cellActions} onClick={(event) => event.stopPropagation()}>
                {isInvited ? (
                    <Button size="compact-sm" variant="light" leftSection={<IconSend size={14}/>} onClick={onResendInvitation} className={classes.inlineAction}>
                        {t`Resend`}
                    </Button>
                ) : (
                    <Button size="compact-sm" variant="subtle" color="gray" leftSection={<IconPencil size={14}/>} onClick={onEdit} className={classes.inlineAction}>
                        {t`Edit`}
                    </Button>
                )}
                <Menu shadow="md" width={210} position="bottom-end">
                    <Menu.Target>
                        <ActionIcon variant="subtle" color="gray" aria-label={t`More actions`} data-testid="team-member-actions">
                            <IconDotsVertical size={16}/>
                        </ActionIcon>
                    </Menu.Target>
                    <Menu.Dropdown>
                        <Menu.Item leftSection={<IconPencil size={14}/>} onClick={onEdit}>
                            {t`Edit user`}
                        </Menu.Item>
                        {canToggleActive && (
                            <Menu.Item
                                leftSection={isInactive ? <IconPlayerPlay size={14}/> : <IconPlayerPause size={14}/>}
                                onClick={onToggleActive}
                            >
                                {isInactive ? t`Reactivate` : t`Deactivate`}
                            </Menu.Item>
                        )}
                        {canResetTwoFactor && (
                            <Menu.Item
                                color="red"
                                leftSection={<IconShieldOff size={14}/>}
                                onClick={onResetTwoFactor}
                                data-testid="team-reset-two-factor-menu-item"
                            >
                                {t`Reset two-factor`}
                            </Menu.Item>
                        )}
                        {isInvited && (
                            <>
                                <Menu.Item leftSection={<IconSend size={14}/>} onClick={onResendInvitation}>
                                    {t`Resend invitation`}
                                </Menu.Item>
                                <Menu.Item color="red" leftSection={<IconTrash size={14}/>} onClick={onRevokeInvitation}>
                                    {t`Revoke invitation`}
                                </Menu.Item>
                            </>
                        )}
                    </Menu.Dropdown>
                </Menu>
            </div>
        </div>
    );
};
