import {Button, Select, Table, Text} from "@mantine/core";
import {IconPlus, IconUserMinus} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {useState} from "react";
import {useDisclosure} from "@mantine/hooks";
import {Card} from "../Card";
import {TableSkeleton} from "../TableSkeleton";
import {InviteUserModal} from "../../modals/InviteUserModal";
import {useGetTeam} from "../../../queries/useGetTeam.ts";
import {useGetUsers} from "../../../queries/useGetUsers.ts";
import {useEditUser} from "../../../mutations/useEditUser.ts";
import {TeamScope} from "../../../api/team.client.ts";
import {TeamMemberAccess, User} from "../../../types.ts";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {confirmationDialog} from "../../../utilites/confirmationDialog.tsx";
import {useAccess} from "../../../hooks/useAccess.ts";
import {roleLabel, teamAccessInitialValues, toTeamAccessPayload} from "../TeamAccessFields";
import classes from "./TeamAccessPanel.module.scss";

interface TeamAccessPanelProps {
    scope: TeamScope;
}

export const TeamAccessPanel = ({scope}: TeamAccessPanelProps) => {
    const canManageTeam = useAccess().can('MANAGE_TEAM');
    const teamQuery = useGetTeam(scope, canManageTeam);
    const usersQuery = useGetUsers(canManageTeam);
    const editMutation = useEditUser();
    const [inviteOpen, {open: openInvite, close: closeInvite}] = useDisclosure(false);
    const [memberToAdd, setMemberToAdd] = useState<string | null>(null);

    const members = teamQuery.data?.data;
    const users = usersQuery.data?.data ?? [];
    const scopeId = Number(scope.id);
    const directVia = scope.type === 'event' ? 'EVENT' : 'ORGANIZER';
    const grantKey = scope.type === 'event' ? 'event_ids' : 'organizer_ids';

    const memberIds = new Set((members ?? []).map(member => member.user_id));
    const addableMembers = users.filter(user =>
        user.access?.scope === 'RESTRICTED' && !memberIds.has(Number(user.id))
    );

    const updateGrants = (user: User, change: (ids: number[]) => number[], successMessage: string) => {
        const access = teamAccessInitialValues(user);
        const role = user.role ?? 'ORGANIZER';

        editMutation.mutate({
            userId: user.id,
            userData: {
                first_name: user.first_name,
                last_name: user.last_name,
                role,
                ...toTeamAccessPayload({...access, role, [grantKey]: change(access[grantKey])}),
            },
        }, {
            onSuccess: () => showSuccess(successMessage),
            onError: (error: any) => showError(error?.response?.data?.errors?.access_scope?.[0]
                ?? error?.response?.data?.message
                ?? t`Something went wrong! Please try again`),
        });
    };

    const handleAdd = () => {
        const user = users.find(candidate => String(candidate.id) === memberToAdd);
        if (!user) {
            return;
        }
        updateGrants(user, ids => [...ids, scopeId], t`Access granted`);
        setMemberToAdd(null);
    };

    const handleRemove = (member: TeamMemberAccess) => {
        const user = users.find(candidate => Number(candidate.id) === member.user_id);
        if (!user) {
            return;
        }
        confirmationDialog(
            t`Remove ${member.email}'s access here?`,
            () => updateGrants(user, ids => ids.filter(id => id !== scopeId), t`Access removed`),
            {confirm: t`Remove access`},
        );
    };

    const accessLabel = (member: TeamMemberAccess) => {
        switch (member.access_via) {
            case 'ACCOUNT':
                return t`Whole account`;
            case 'ORGANIZER':
                return scope.type === 'event' ? t`Through the organizer` : t`This organizer`;
            default:
                return t`This event only`;
        }
    };

    if (!canManageTeam) {
        return <Text c="dimmed">{t`Only admins can manage team access.`}</Text>;
    }

    return (
        <>
            <Card className={classes.toolbar}>
                <div className={classes.toolbarRow}>
                    <div className={classes.addExisting}>
                        <Select
                            className={classes.memberSelect}
                            placeholder={t`Give an existing member access`}
                            data={addableMembers.map(user => ({value: String(user.id), label: `${user.first_name} ${user.last_name ?? ''} (${user.email})`}))}
                            value={memberToAdd}
                            onChange={setMemberToAdd}
                            searchable
                            disabled={addableMembers.length === 0}
                        />
                        <Button
                            variant="light"
                            disabled={!memberToAdd}
                            loading={editMutation.isPending}
                            onClick={handleAdd}
                        >
                            {t`Add`}
                        </Button>
                    </div>
                    <Button color="green" leftSection={<IconPlus size={16}/>} onClick={openInvite} data-testid="team-invite-to-scope-button">
                        {t`Invite someone`}
                    </Button>
                </div>
            </Card>

            <TableSkeleton isVisible={!members}/>

            {members && (
                <Card className={classes.tableCard}>
                    <Table.ScrollContainer minWidth={360}>
                        <Table verticalSpacing="sm" data-testid="team-access-table">
                            <Table.Thead>
                                <Table.Tr>
                                    <Table.Th>{t`Person`}</Table.Th>
                                    <Table.Th>{t`Access`}</Table.Th>
                                    <Table.Th/>
                                </Table.Tr>
                            </Table.Thead>
                            <Table.Tbody>
                                {members.map(member => (
                                    <Table.Tr key={member.user_id}>
                                        <Table.Td>
                                            <Text fz="sm" fw={500}>{member.first_name} {member.last_name}</Text>
                                            <Text fz="xs" c="dimmed" className={classes.email}>{member.email}</Text>
                                            <Text fz="xs" fw={500}>{roleLabel(member.role)}</Text>
                                        </Table.Td>
                                        <Table.Td>
                                            <Text fz="sm" fw={500} c={member.access_via === directVia ? 'blue' : 'dimmed'}>
                                                {accessLabel(member)}
                                            </Text>
                                        </Table.Td>
                                        <Table.Td width={60}>
                                            {member.access_via === directVia && (
                                                <Button
                                                    variant="subtle"
                                                    color="red"
                                                    size="xs"
                                                    leftSection={<IconUserMinus size={14}/>}
                                                    onClick={() => handleRemove(member)}
                                                    disabled={editMutation.isPending}
                                                    data-testid="team-remove-access-button"
                                                >
                                                    {t`Remove`}
                                                </Button>
                                            )}
                                        </Table.Td>
                                    </Table.Tr>
                                ))}
                            </Table.Tbody>
                        </Table>
                    </Table.ScrollContainer>
                </Card>
            )}

            {inviteOpen && (
                <InviteUserModal
                    onClose={closeInvite}
                    initialAccess={{access_scope: 'RESTRICTED', [grantKey]: [scopeId]}}
                />
            )}
        </>
    );
};
