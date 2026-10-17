import {Button, Loader, Tooltip, UnstyledButton} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {IconDeviceDesktop, IconDeviceMobile, IconX} from "@tabler/icons-react";
import {useEffect, useState} from "react";
import classNames from "classnames";
import {useGetMe} from "../../../../queries/useGetMe.ts";
import {useGetTwoFactorStatus} from "../../../../queries/useGetTwoFactorStatus.ts";
import {useRevokeTrustedDevice, useRevokeTrustedDevices} from "../../../../mutations/useTwoFactorMutations.ts";
import {formatDateWithLocale, relativeDate} from "../../../../utilites/dates.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {confirmationDialog} from "../../../../utilites/confirmationDialog.tsx";
import {Callout} from "../../../common/Callout";
import {SettingsPage, SettingsRow, SettingsSection} from "../../../common/SettingsPage";
import {TrustedDevice, User} from "../../../../types.ts";
import {EnableTwoFactorModal} from "./EnableTwoFactorModal.tsx";
import {DisableTwoFactorModal} from "./DisableTwoFactorModal.tsx";
import {RegenerateRecoveryCodesModal} from "./RegenerateRecoveryCodesModal.tsx";
import {ChangePasswordModal} from "./ChangePasswordModal.tsx";
import {describeDevice} from "./describeDevice.ts";
import {SignInMethods} from "./SignInMethods.tsx";
import {useResumeAction} from "../../../../hooks/useResumeAction.ts";
import classes from "./Security.module.scss";

type OpenModal = 'password' | 'enable' | 'disable' | 'regenerate' | null;

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

interface TwoFactorRowsProps {
    me?: User;
    onOpen: (modal: OpenModal) => void;
}

const TwoFactorRows = ({me, onOpen}: TwoFactorRowsProps) => {
    const {data: status, isLoading, isError, refetch} = useGetTwoFactorStatus();
    const revokeAll = useRevokeTrustedDevices();

    if (isError) {
        return (
            <SettingsRow
                label={t`Two-factor authentication`}
                description={t`Couldn't load your security settings`}
            >
                <Button variant="default" onClick={() => refetch()}>{t`Try again`}</Button>
            </SettingsRow>
        );
    }

    if (isLoading || !status) {
        return (
            <SettingsRow label={t`Two-factor authentication`}>
                <Loader size="sm"/>
            </SettingsRow>
        );
    }

    const requiredBy = status.required_by_accounts;
    const requiredBySso = status.required_by_sso_accounts;
    const isRequired = requiredBy.length > 0 || requiredBySso.length > 0;
    const lowOnCodes = status.enabled && status.recovery_codes_remaining <= 3;
    const codePercentage = Math.round((status.recovery_codes_remaining / status.recovery_codes_total) * 100);

    const handleRevokeAll = () => {
        confirmationDialog(t`Every trusted device will need a code at its next sign-in. Continue?`, () => {
            revokeAll.mutate(undefined, {
                onSuccess: () => showSuccess(t`All trusted devices removed`),
                onError: () => showError(t`Something went wrong. Please try again.`),
            });
        });
    };

    return (
        <>
            <SettingsRow
                label={
                    <span className={classes.labelWithStatus}>
                        <span>{t`Two-factor authentication`}</span>
                        <span
                            className={classNames(classes.statusPill, {[classes.statusOn]: status.enabled})}
                            data-testid="two-factor-status"
                        >
                            {status.enabled ? t`On` : t`Off`}
                        </span>
                    </span>
                }
                description={status.enabled && status.confirmed_at
                    ? <Trans>Your account asks for a code from your authenticator app at sign-in. Turned on {formatDateWithLocale(status.confirmed_at, 'shortDate', me?.timezone ?? 'UTC', me?.locale)}.</Trans>
                    : t`Protect your account with a second step at sign-in. Even if someone learns your password, they can't get in without the code from your phone.`}
                below={!status.enabled && isRequired && (
                    <Callout variant="warning" title={t`Required by your organization`}>
                        {requiredBy.length > 0
                            ? <Trans>{requiredBy.join(', ')} requires two-factor authentication for everyone on the team.</Trans>
                            : <Trans>{requiredBySso.join(', ')} requires single sign-on. As the owner you sign in with your password, so two-factor authentication protects that sign-in.</Trans>}
                    </Callout>
                )}
            >
                {!status.enabled && (
                    <Button onClick={() => onOpen('enable')} data-testid="two-factor-enable-button">
                        {t`Turn on`}
                    </Button>
                )}
            </SettingsRow>

            {status.enabled && (
                <>
                    <SettingsRow
                        label={t`Recovery codes`}
                        description={(
                            <>
                                <span data-testid="two-factor-recovery-remaining">
                                    {t`${status.recovery_codes_remaining} of ${status.recovery_codes_total} remaining`}
                                </span>
                                <span className={classes.meter}>
                                    <span
                                        className={classNames(classes.meterFill, {[classes.meterLow]: lowOnCodes})}
                                        style={{width: `${codePercentage}%`}}
                                    />
                                </span>
                                {lowOnCodes && (
                                    <span className={classes.rowWarning}>
                                        {t`You're running low. Generate a new set so you don't get locked out.`}
                                    </span>
                                )}
                            </>
                        )}
                    >
                        <Button
                            variant={lowOnCodes ? 'filled' : 'default'}
                            onClick={() => onOpen('regenerate')}
                            data-testid="two-factor-regenerate-button"
                        >
                            {t`Generate new codes`}
                        </Button>
                    </SettingsRow>

                    <SettingsRow
                        label={t`Trusted devices`}
                        description={status.trusted_devices.length === 0
                            ? t`Tick "Trust this device" when you sign in to skip the code on that browser for 30 days.`
                            : t`These browsers skip the code for 30 days after you trust them.`}
                        below={status.trusted_devices.length > 0 && (
                            <ul className={classes.devices}>
                                {status.trusted_devices.map(device => (
                                    <TrustedDeviceRow key={device.id} device={device}/>
                                ))}
                            </ul>
                        )}
                    >
                        {status.trusted_devices.length > 0 && (
                            <Button
                                variant="default"
                                loading={revokeAll.isPending}
                                onClick={handleRevokeAll}
                                data-testid="two-factor-revoke-devices"
                            >
                                {t`Remove all`}
                            </Button>
                        )}
                    </SettingsRow>

                    <SettingsRow
                        label={t`Turn off two-factor authentication`}
                        description={requiredBy.length > 0
                            ? <Trans>{requiredBy.join(', ')} requires two-factor authentication, so it can't be turned off.</Trans>
                            : requiredBySso.length > 0
                                ? <Trans>{requiredBySso.join(', ')} requires single sign-on. As the owner you sign in with your password, so two-factor authentication protects that sign-in and can't be turned off.</Trans>
                                : t`Lost your phone or switching apps? Turn it off, then on again to connect a new authenticator.`}
                    >
                        <Button
                            variant="outline"
                            color="red"
                            disabled={isRequired}
                            onClick={() => onOpen('disable')}
                            data-testid="two-factor-disable-button"
                        >
                            {t`Turn off`}
                        </Button>
                    </SettingsRow>
                </>
            )}
        </>
    );
};

const SecuritySettings = () => {
    const {data: me} = useGetMe();
    const [openModal, setOpenModal] = useState<OpenModal>(null);
    const closeModal = () => setOpenModal(null);
    const resumeAction = useResumeAction();

    useEffect(() => {
        if (resumeAction === 'enable-2fa') {
            setOpenModal('enable');
        }

        if (resumeAction === 'disable-2fa') {
            setOpenModal('disable');
        }
    }, [resumeAction]);

    return (
        <SettingsPage
            title={t`Security`}
            description={t`How you sign in, and two-factor authentication. They protect every account you belong to.`}
        >
            <SettingsSection>
                {me && <SignInMethods me={me} resumeAction={resumeAction} onChangePassword={() => setOpenModal('password')}/>}
            </SettingsSection>

            <SettingsSection>
                <TwoFactorRows me={me} onOpen={setOpenModal}/>
            </SettingsSection>

            {openModal === 'password' && <ChangePasswordModal onClose={closeModal}/>}
            {openModal === 'enable' && <EnableTwoFactorModal email={me?.email} onClose={closeModal}/>}
            {openModal === 'disable' && <DisableTwoFactorModal onClose={closeModal}/>}
            {openModal === 'regenerate' && <RegenerateRecoveryCodesModal email={me?.email} onClose={closeModal}/>}
        </SettingsPage>
    );
};

export default SecuritySettings;
