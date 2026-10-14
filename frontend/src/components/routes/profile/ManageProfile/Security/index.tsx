import {Button, Loader, Tooltip, UnstyledButton} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {
    IconDeviceDesktop,
    IconDeviceMobile,
    IconKey,
    IconShieldCheck,
    IconShieldLock,
    IconShieldOff,
    IconX,
} from "@tabler/icons-react";
import {useState} from "react";
import classNames from "classnames";
import {useGetTwoFactorStatus} from "../../../../../queries/useGetTwoFactorStatus.ts";
import {useRevokeTrustedDevice, useRevokeTrustedDevices} from "../../../../../mutations/useTwoFactorMutations.ts";
import {formatDateWithLocale, relativeDate} from "../../../../../utilites/dates.ts";
import {showError, showSuccess} from "../../../../../utilites/notifications.tsx";
import {confirmationDialog} from "../../../../../utilites/confirmationDialog.tsx";
import {Callout} from "../../../../common/Callout";
import {TrustedDevice, User} from "../../../../../types.ts";
import {EnableTwoFactorModal} from "./EnableTwoFactorModal.tsx";
import {DisableTwoFactorModal} from "./DisableTwoFactorModal.tsx";
import {RegenerateRecoveryCodesModal} from "./RegenerateRecoveryCodesModal.tsx";
import {describeDevice} from "./describeDevice.ts";
import classes from "./Security.module.scss";

type OpenModal = 'enable' | 'disable' | 'regenerate' | null;

interface SecurityProps {
    me?: User;
}

const TrustedDeviceRow = ({device}: { device: TrustedDevice }) => {
    const revoke = useRevokeTrustedDevice();
    const {label, isMobile} = describeDevice(device.user_agent);

    return (
        <li className={classes.device} data-testid="two-factor-trusted-device">
            <div className={classes.deviceIcon}>
                {isMobile ? <IconDeviceMobile size={18}/> : <IconDeviceDesktop size={18}/>}
            </div>
            <div className={classes.deviceText}>
                <div className={classes.deviceName}>
                    {label}
                    {device.is_current_device && <span className={classes.currentDevice}>{t`This device`}</span>}
                </div>
                <div className={classes.deviceMeta}>
                    {device.last_used_at && <span><Trans>Last used {relativeDate(device.last_used_at)}</Trans></span>}
                    {device.ip_address && <span>{device.ip_address}</span>}
                </div>
            </div>
            <Tooltip label={t`Stop trusting this device`} withArrow>
                <UnstyledButton
                    className={classes.deviceRevoke}
                    aria-label={t`Stop trusting this device`}
                    disabled={revoke.isPending}
                    onClick={() => revoke.mutate(device.id, {
                        onSuccess: () => showSuccess(t`Device removed`),
                        onError: () => showError(t`Something went wrong. Please try again.`),
                    })}
                >
                    <IconX size={16}/>
                </UnstyledButton>
            </Tooltip>
        </li>
    );
};

export const Security = ({me}: SecurityProps) => {
    const {data: status, isLoading, isError, refetch} = useGetTwoFactorStatus();
    const revokeAll = useRevokeTrustedDevices();
    const [openModal, setOpenModal] = useState<OpenModal>(null);

    if (isError) {
        return (
            <Callout variant="warning" title={t`Couldn't load your security settings`}>
                <Button size="xs" variant="light" onClick={() => refetch()}>{t`Try again`}</Button>
            </Callout>
        );
    }

    if (isLoading || !status) {
        return <div className={classes.loading}><Loader size="sm"/></div>;
    }

    const requiredBy = status.required_by_accounts;
    const isRequired = requiredBy.length > 0;
    const lowOnCodes = status.enabled && status.recovery_codes_remaining <= 3;
    const codePercentage = Math.round((status.recovery_codes_remaining / status.recovery_codes_total) * 100);
    const locale = me?.locale;

    const handleRevokeAll = () => {
        confirmationDialog(t`Every trusted device will need a code at its next sign-in. Continue?`, () => {
            revokeAll.mutate(undefined, {
                onSuccess: () => showSuccess(t`All trusted devices removed`),
                onError: () => showError(t`Something went wrong. Please try again.`),
            });
        });
    };

    return (
        <div className={classes.security}>
            <section className={classNames(classes.hero, {[classes.heroEnabled]: status.enabled})}>
                <div className={classes.heroIcon}>
                    {status.enabled ? <IconShieldCheck size={28} stroke={1.75}/> : <IconShieldLock size={28} stroke={1.75}/>}
                </div>
                <div className={classes.heroContent}>
                    <div className={classes.heroTitleRow}>
                        <h3 className={classes.heroTitle}>{t`Two-factor authentication`}</h3>
                        <span
                            className={classNames(classes.statusPill, {[classes.statusOn]: status.enabled})}
                            data-testid="two-factor-status"
                        >
                            {status.enabled ? t`On` : t`Off`}
                        </span>
                    </div>
                    <p className={classes.heroText}>
                        {status.enabled && status.confirmed_at
                            ? <Trans>Your account asks for a code from your authenticator app at sign-in. Turned on {formatDateWithLocale(status.confirmed_at, 'shortDate', me?.timezone ?? 'UTC', locale)}.</Trans>
                            : t`Protect your account with a second step at sign-in. Even if someone learns your password, they can't get in without the code from your phone.`}
                    </p>
                    {!status.enabled && (
                        <Button
                            className={classes.heroButton}
                            leftSection={<IconShieldLock size={16}/>}
                            onClick={() => setOpenModal('enable')}
                            data-testid="two-factor-enable-button"
                        >
                            {t`Turn on two-factor authentication`}
                        </Button>
                    )}
                </div>
            </section>

            {!status.enabled && isRequired && (
                <Callout variant="warning" title={t`Required by your organisation`}>
                    <Trans>{requiredBy.join(', ')} requires two-factor authentication for everyone on the team.</Trans>
                </Callout>
            )}

            {status.enabled && (
                <div className={classes.rows}>
                    <div className={classes.row}>
                        <div className={classes.rowHeader}>
                            <div className={classes.rowIcon}><IconKey size={18}/></div>
                            <div className={classes.rowContent}>
                                <div className={classes.rowTitle}>{t`Recovery codes`}</div>
                                <div className={classes.rowText} data-testid="two-factor-recovery-remaining">
                                    {t`${status.recovery_codes_remaining} of ${status.recovery_codes_total} remaining`}
                                </div>
                            </div>
                            <div className={classes.rowAction}>
                                <Button
                                    variant={lowOnCodes ? 'filled' : 'default'}
                                    size="xs"
                                    onClick={() => setOpenModal('regenerate')}
                                    data-testid="two-factor-regenerate-button"
                                >
                                    {t`Generate new codes`}
                                </Button>
                            </div>
                        </div>
                        <div className={classes.rowExtra}>
                            <div className={classes.meter}>
                                <div
                                    className={classNames(classes.meterFill, {[classes.meterLow]: lowOnCodes})}
                                    style={{width: `${codePercentage}%`}}
                                />
                            </div>
                            {lowOnCodes && (
                                <div className={classes.rowWarning}>
                                    {t`You're running low. Generate a new set so you don't get locked out.`}
                                </div>
                            )}
                        </div>
                    </div>

                    <div className={classes.row}>
                        <div className={classes.rowHeader}>
                            <div className={classes.rowIcon}><IconDeviceDesktop size={18}/></div>
                            <div className={classes.rowContent}>
                                <div className={classes.rowTitle}>{t`Trusted devices`}</div>
                                <div className={classes.rowText}>
                                    {status.trusted_devices.length === 0
                                        ? t`Tick "Trust this device" when you sign in to skip the code on that browser for 30 days.`
                                        : t`These browsers skip the code for 30 days after you trust them.`}
                                </div>
                            </div>
                            {status.trusted_devices.length > 0 && (
                                <div className={classes.rowAction}>
                                    <Button
                                        variant="default"
                                        size="xs"
                                        loading={revokeAll.isPending}
                                        onClick={handleRevokeAll}
                                        data-testid="two-factor-revoke-devices"
                                    >
                                        {t`Remove all`}
                                    </Button>
                                </div>
                            )}
                        </div>
                        {status.trusted_devices.length > 0 && (
                            <ul className={classNames(classes.rowExtra, classes.devices)}>
                                {status.trusted_devices.map(device => (
                                    <TrustedDeviceRow key={device.id} device={device}/>
                                ))}
                            </ul>
                        )}
                    </div>

                    <div className={classNames(classes.row, classes.dangerRow)}>
                        <div className={classes.rowHeader}>
                            <div className={classes.rowIcon}><IconShieldOff size={18}/></div>
                            <div className={classes.rowContent}>
                                <div className={classes.rowTitle}>{t`Turn off two-factor authentication`}</div>
                                <div className={classes.rowText}>
                                    {isRequired
                                        ? <Trans>{requiredBy.join(', ')} requires two-factor authentication, so it can't be turned off.</Trans>
                                        : t`Lost your phone or switching apps? Turn it off, then on again to connect a new authenticator.`}
                                </div>
                            </div>
                            <div className={classes.rowAction}>
                                <Button
                                    variant="outline"
                                    color="red"
                                    size="xs"
                                    disabled={isRequired}
                                    onClick={() => setOpenModal('disable')}
                                    data-testid="two-factor-disable-button"
                                >
                                    {t`Turn off`}
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {openModal === 'enable' && <EnableTwoFactorModal email={me?.email} onClose={() => setOpenModal(null)}/>}
            {openModal === 'disable' && <DisableTwoFactorModal onClose={() => setOpenModal(null)}/>}
            {openModal === 'regenerate' && (
                <RegenerateRecoveryCodesModal email={me?.email} onClose={() => setOpenModal(null)}/>
            )}
        </div>
    );
};
