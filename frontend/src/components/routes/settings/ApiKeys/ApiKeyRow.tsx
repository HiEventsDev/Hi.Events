import {ActionIcon, Badge, Tooltip, VisuallyHidden} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconKey, IconTrash} from "@tabler/icons-react";
import classNames from "classnames";
import dayjs from "dayjs";
import {ApiKey} from "../../../../types.ts";
import {formatDateWithLocale, relativeDate} from "../../../../utilites/dates.ts";
import classes from "./ApiKeys.module.scss";

const EXPIRING_SOON_DAYS = 7;

interface ApiKeyRowProps {
    apiKey: ApiKey;
    timezone: string;
    locale?: string;
    showOwner: boolean;
    onRevoke: () => void;
}

const ExpiryCell = ({apiKey, timezone, locale}: Pick<ApiKeyRowProps, 'apiKey' | 'timezone' | 'locale'>) => {
    if (!apiKey.expires_at) {
        return <><span className={classes.cellLabel}>{t`Expires`}</span>{t`Never`}</>;
    }

    if (apiKey.is_expired) {
        return <span className={classes.expired}>{t`Expired ${relativeDate(apiKey.expires_at)}`}</span>;
    }

    if (dayjs.utc(apiKey.expires_at).diff(dayjs(), 'day', true) <= EXPIRING_SOON_DAYS) {
        return <span className={classes.expiringSoon}>{t`Expires ${relativeDate(apiKey.expires_at)}`}</span>;
    }

    return <><span className={classes.cellLabel}>{t`Expires`}</span>{formatDateWithLocale(apiKey.expires_at, 'shortDate', timezone, locale)}</>;
};

export const ApiKeyRow = ({apiKey, timezone, locale, showOwner, onRevoke}: ApiKeyRowProps) => {
    const revokeLabel = apiKey.is_expired ? t`Remove ${apiKey.name}` : t`Revoke ${apiKey.name}`;

    return (
        <div className={classNames(classes.row, {[classes.rowExpired]: apiKey.is_expired})} data-testid="api-key-row">
            <div className={classes.identity}>
                <span className={classes.keyIcon}><IconKey size={18}/></span>
                <div className={classes.identityText}>
                    <div className={classes.nameLine}>
                        <span className={classes.name} title={apiKey.name}>{apiKey.name}</span>
                        <Badge
                            size="xs"
                            variant="light"
                            color={apiKey.access === 'READ_WRITE' ? 'primary' : 'gray'}
                            data-testid="api-key-access"
                        >
                            {apiKey.access === 'READ_WRITE' ? t`Read and write` : t`Read-only`}
                        </Badge>
                    </div>
                    <div className={classes.subLine}>
                        <span className={classes.hint} aria-hidden>hie_…{apiKey.token_hint}</span>
                        <VisuallyHidden>{t`Key ending in ${apiKey.token_hint}`}</VisuallyHidden>
                        {showOwner && apiKey.owner && (
                            <span className={classes.owner} title={apiKey.owner.email}>
                                {apiKey.is_own ? t`You` : apiKey.owner.full_name}
                            </span>
                        )}
                    </div>
                </div>
            </div>

            <div className={classes.meta}>
                <span className={classes.cell}>
                    <span className={classes.cellLabel}>{t`Created`}</span>
                    {formatDateWithLocale(apiKey.created_at, 'shortDate', timezone, locale)}
                </span>
                <span className={classes.cell}>
                    <span className={classes.cellLabel}>{t`Last used`}</span>
                    {apiKey.last_used_at ? relativeDate(apiKey.last_used_at) : t`Never used`}
                </span>
                <span className={classNames(classes.cell, classes.cellExpires)}>
                    <ExpiryCell apiKey={apiKey} timezone={timezone} locale={locale}/>
                </span>
            </div>

            <div className={classes.cellActions}>
                <Tooltip label={apiKey.is_expired ? t`Remove` : t`Revoke`} withArrow>
                    <ActionIcon
                        variant="subtle"
                        color="red"
                        aria-label={revokeLabel}
                        onClick={onRevoke}
                        data-testid="api-key-revoke"
                    >
                        <IconTrash size={16}/>
                    </ActionIcon>
                </Tooltip>
            </div>
        </div>
    );
};
