import {Button, Collapse, PasswordInput, UnstyledButton} from "@mantine/core";
import {useClipboard} from "@mantine/hooks";
import {t, Trans} from "@lingui/macro";
import {IconArrowLeft, IconCheck, IconChevronDown, IconCopy, IconDeviceMobile} from "@tabler/icons-react";
import QRCode from "react-qr-code";
import {FormEvent, useState} from "react";
import classNames from "classnames";
import {OneTimeCodeInput} from "../OneTimeCodeInput";
import {RecoveryCodesDisplay} from "./RecoveryCodesDisplay.tsx";
import {AuthenticatorApps} from "./AuthenticatorApps.tsx";
import {useBeginTwoFactorSetup, useConfirmTwoFactorSetup} from "../../../mutations/useTwoFactorMutations.ts";
import {showError} from "../../../utilites/notifications.tsx";
import {TwoFactorSetup} from "../../../types.ts";
import classes from "./TwoFactor.module.scss";

type SetupStep = 'scan' | 'verify' | 'recovery';

interface TwoFactorSetupFlowProps {
    email?: string;
    onComplete: () => void;
}

const formatSecret = (secret: string) => secret.match(/.{1,4}/g)?.join(' ') ?? secret;

const extractError = (error: any): string => error?.response?.data?.errors?.code?.[0]
    ?? error?.response?.data?.message
    ?? t`Something went wrong. Please try again.`;

export const TwoFactorSetupFlow = ({email, onComplete}: TwoFactorSetupFlowProps) => {
    const [step, setStep] = useState<SetupStep>('scan');
    const [setup, setSetup] = useState<TwoFactorSetup | null>(null);
    const [code, setCode] = useState('');
    const [codeError, setCodeError] = useState<string | null>(null);
    const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);
    const [acknowledged, setAcknowledged] = useState(false);
    const [manualEntryOpen, setManualEntryOpen] = useState(false);
    const beginSetup = useBeginTwoFactorSetup();
    const confirmSetup = useConfirmTwoFactorSetup();
    const clipboard = useClipboard({timeout: 2000});

    const [password, setPassword] = useState('');
    const [passwordError, setPasswordError] = useState<string | null>(null);

    const startSetup = (event: FormEvent) => {
        event.preventDefault();
        beginSetup.mutate(password, {
            onSuccess: (data) => {
                setPassword('');
                setSetup(data);
            },
            onError: (error: any) => {
                const passwordMessage = error?.response?.data?.errors?.password?.[0];
                if (passwordMessage) {
                    setPasswordError(passwordMessage);
                    return;
                }
                showError(extractError(error));
            },
        });
    };

    const verify = (value: string = code) => {
        if (value.length !== 6 || confirmSetup.isPending) {
            return;
        }

        confirmSetup.mutate(value, {
            onSuccess: (codes) => {
                setRecoveryCodes(codes);
                setStep('recovery');
            },
            onError: (error: any) => {
                setCode('');
                setCodeError(extractError(error));
            },
        });
    };

    const steps: { key: SetupStep, label: string }[] = [
        {key: 'scan', label: t`Scan`},
        {key: 'verify', label: t`Verify`},
        {key: 'recovery', label: t`Save codes`},
    ];
    const currentIndex = steps.findIndex(item => item.key === step);

    return (
        <div className={classes.setupFlow}>
            <ol className={classes.stepper}>
                {steps.map((item, index) => (
                    <li
                        key={item.key}
                        className={classNames(classes.stepperItem, {
                            [classes.stepperDone]: index < currentIndex,
                            [classes.stepperCurrent]: index === currentIndex,
                        })}
                    >
                        <span className={classes.stepperDot}>
                            {index < currentIndex ? <IconCheck size={12} stroke={3}/> : index + 1}
                        </span>
                        {item.label}
                    </li>
                ))}
            </ol>

            {step === 'scan' && !setup && (
                <form className={classes.stepBody} onSubmit={startSetup}>
                    <div>
                        <h3 className={classes.sectionTitle}>{t`Confirm it's you`}</h3>
                        <p className={classes.sectionHint}>{t`Enter your password to start setting up two-factor authentication.`}</p>
                    </div>
                    <PasswordInput
                        label={t`Current password`}
                        value={password}
                        onChange={(event) => {
                            setPassword(event.currentTarget.value);
                            setPasswordError(null);
                        }}
                        error={passwordError}
                        autoComplete="current-password"
                        autoFocus
                        required
                        data-testid="two-factor-setup-password"
                    />
                    <Button
                        type="submit"
                        fullWidth
                        loading={beginSetup.isPending}
                        disabled={password.length === 0}
                        data-testid="two-factor-setup-password-submit"
                    >
                        {t`Continue`}
                    </Button>
                </form>
            )}

            {step === 'scan' && setup && (
                <div className={classes.stepBody}>
                    <section className={classes.setupSection}>
                        <h3 className={classes.sectionTitle}>{t`1. Get an authenticator app`}</h3>
                        <p className={classes.sectionHint}>
                            {t`Any app that supports time-based codes (TOTP) will work. Already have one? Skip ahead.`}
                        </p>
                        <AuthenticatorApps/>
                    </section>

                    <section className={classes.setupSection}>
                        <h3 className={classes.sectionTitle}>{t`2. Scan this QR code`}</h3>
                        <div className={classes.qrRow}>
                            <div className={classes.qrFrame}>
                                <QRCode value={setup.otpauth_uri} size={168} level="M"/>
                            </div>
                            <div className={classes.qrHelp}>
                                <p className={classes.sectionHint}>
                                    {t`Open your authenticator app, tap the + button and scan the code.`}
                                </p>
                                <a href={setup.otpauth_uri} className={classes.openInApp}>
                                    <IconDeviceMobile size={16}/>
                                    {t`On your phone? Open in authenticator app`}
                                </a>
                                <UnstyledButton
                                    className={classes.manualToggle}
                                    onClick={() => setManualEntryOpen(open => !open)}
                                    data-expanded={manualEntryOpen}
                                    data-testid="two-factor-manual-entry-toggle"
                                >
                                    {t`Can't scan it? Enter a setup key instead`}
                                    <IconChevronDown size={14}/>
                                </UnstyledButton>
                                <Collapse expanded={manualEntryOpen}>
                                    <div className={classes.secretBox}>
                                        <code data-testid="two-factor-secret">{formatSecret(setup.secret)}</code>
                                        <UnstyledButton
                                            className={classes.copyButton}
                                            onClick={() => clipboard.copy(setup.secret)}
                                            aria-label={t`Copy setup key`}
                                        >
                                            {clipboard.copied ? <IconCheck size={16}/> : <IconCopy size={16}/>}
                                        </UnstyledButton>
                                    </div>
                                </Collapse>
                            </div>
                        </div>
                    </section>

                    <Button
                        fullWidth
                        onClick={() => setStep('verify')}
                        data-testid="two-factor-setup-next"
                    >
                        {t`I've added it, continue`}
                    </Button>
                </div>
            )}

            {step === 'verify' && (
                <div className={classes.stepBody}>
                    <div className={classes.centered}>
                        <h3 className={classes.sectionTitle}>{t`Enter the 6-digit code`}</h3>
                        <p className={classes.sectionHint}>
                            <Trans>Type the code your authenticator app shows for {email ?? t`your account`}.</Trans>
                        </p>
                    </div>
                    <OneTimeCodeInput
                        label={t`Authentication code`}
                        value={code}
                        onChange={(value) => {
                            setCode(value);
                            setCodeError(null);
                        }}
                        onComplete={verify}
                        error={codeError}
                        disabled={confirmSetup.isPending}
                        autoFocus
                        dataTestId="two-factor-setup-code"
                    />
                    <Button
                        fullWidth
                        loading={confirmSetup.isPending}
                        disabled={code.length !== 6}
                        onClick={() => verify()}
                        data-testid="two-factor-setup-verify"
                    >
                        {t`Verify and turn on`}
                    </Button>
                    <UnstyledButton className={classes.backLink} onClick={() => setStep('scan')}>
                        <IconArrowLeft size={14}/>
                        {t`Back to QR code`}
                    </UnstyledButton>
                </div>
            )}

            {step === 'recovery' && (
                <div className={classes.stepBody}>
                    <div className={classes.successBanner}>
                        <IconCheck size={18} stroke={2.5}/>
                        {t`Two-factor authentication is on`}
                    </div>
                    <RecoveryCodesDisplay
                        codes={recoveryCodes}
                        email={email}
                        acknowledged={acknowledged}
                        onAcknowledgedChange={setAcknowledged}
                    />
                    <Button fullWidth disabled={!acknowledged} onClick={onComplete} data-testid="two-factor-setup-done">
                        {t`Done`}
                    </Button>
                </div>
            )}
        </div>
    );
};
