import {ActionIcon, Badge, Button, Skeleton, Tooltip} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconPlugConnected, IconTrash} from "@tabler/icons-react";
import {useGetOAuthConnections} from "../../../../queries/useGetOAuthConnections.ts";
import {useDisconnectOAuthConnection} from "../../../../mutations/useOAuthMutations.ts";
import {confirmationDialog} from "../../../../utilites/confirmationDialog.tsx";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {formatDateWithLocale, relativeDate} from "../../../../utilites/dates.ts";
import {OAuthConnection} from "../../../../types.ts";
import {CodeSnippet} from "./CodeSnippet.tsx";
import classes from "./ApiKeys.module.scss";

interface ConnectedAppsProps {
    mcpUrl: string;
    timezone: string;
    locale?: string;
}

export const ConnectedApps = ({mcpUrl, timezone, locale}: ConnectedAppsProps) => {
    const {data: connections, isLoading, isError, refetch} = useGetOAuthConnections();
    const disconnect = useDisconnectOAuthConnection();

    const confirmDisconnect = (connection: OAuthConnection) => {
        const name = connection.app_name ?? t`this app`;

        confirmationDialog(
            t`Disconnect ${name}? It stops working with your account straight away.`,
            () => disconnect.mutate(connection.id, {
                onSuccess: () => showSuccess(t`App disconnected`),
                onError: (error: any) => showError(error?.response?.data?.message ?? t`Something went wrong. Please try again.`),
            }),
            {confirm: t`Disconnect`, destructive: true},
        );
    };

    const isEmpty = !isLoading && !isError && (connections ?? []).length === 0;

    return (
        <section className={classes.connectedApps} data-testid="connected-apps">
            <div className={classes.sectionHeader}>
                <h2 className={classes.sectionTitle}>{t`Connected apps`}</h2>
                <p className={classes.sectionText}>
                    {t`AI assistants such as Claude and ChatGPT connect by signing in, without a key. Add this MCP server URL to your assistant to connect it.`}
                </p>
                <CodeSnippet code={mcpUrl}/>
            </div>

            <div className={classes.panel} aria-busy={isLoading}>
                {isLoading && <div className={classes.row}><Skeleton height={38} radius="md"/></div>}

                {isError && (
                    <div className={classes.empty}>
                        <div className={classes.emptyTitle}>{t`Couldn't load your connected apps`}</div>
                        <Button variant="default" size="xs" onClick={() => refetch()}>{t`Try again`}</Button>
                    </div>
                )}

                {isEmpty && (
                    <div className={classes.empty} data-testid="connected-apps-empty">
                        <div className={classes.emptyIcon}><IconPlugConnected size={22}/></div>
                        <div className={classes.emptyTitle}>{t`No connected apps`}</div>
                    </div>
                )}

                {(connections ?? []).map(connection => (
                    <div className={classes.row} key={connection.id} data-testid="connected-app-row">
                        <div className={classes.identity}>
                            <span className={classes.keyIcon}><IconPlugConnected size={18}/></span>
                            <div className={classes.identityText}>
                                <div className={classes.nameLine}>
                                    <span className={classes.name}>{connection.app_name ?? t`Unnamed app`}</span>
                                    <Badge size="xs" variant="light" color={connection.access === 'READ_WRITE' ? 'primary' : 'gray'}>
                                        {connection.access === 'READ_WRITE' ? t`Read and write` : t`Read-only`}
                                    </Badge>
                                </div>
                                {connection.redirect_host && (
                                    <div className={classes.subLine}>
                                        <span className={classes.hint}>
                                            {connection.redirect_host === 'localhost' ? t`App on your computer` : connection.redirect_host}
                                        </span>
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className={classes.meta}>
                            <span className={classes.cell}>
                                <span className={classes.cellLabel}>{t`Connected`}</span>
                                {formatDateWithLocale(connection.created_at, 'shortDate', timezone, locale)}
                            </span>
                            <span className={classes.cell}>
                                <span className={classes.cellLabel}>{t`Last active`}</span>
                                {connection.last_used_at ? relativeDate(connection.last_used_at) : t`Never`}
                            </span>
                            <span className={classes.cell}/>
                        </div>

                        <div className={classes.cellActions}>
                            <Tooltip label={t`Disconnect`} withArrow>
                                <ActionIcon
                                    variant="subtle"
                                    color="red"
                                    aria-label={t`Disconnect ${connection.app_name ?? ''}`}
                                    onClick={() => confirmDisconnect(connection)}
                                    data-testid="connected-app-disconnect"
                                >
                                    <IconTrash size={16}/>
                                </ActionIcon>
                            </Tooltip>
                        </div>
                    </div>
                ))}
            </div>
        </section>
    );
};
