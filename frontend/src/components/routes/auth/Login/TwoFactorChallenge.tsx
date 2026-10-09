import {Button, Checkbox, TextInput, UnstyledButton} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconArrowLeft, IconKey, IconLifebuoy, IconShieldLock} from "@tabler/icons-react";
import {FormEvent, useState} from "react";
import {OneTimeCodeInput} from "../../../common/OneTimeCodeInput";
import classes from "./Login.module.scss";

type ChallengeMode = 'code' | 'recovery';

interface TwoFactorChallengeProps {
    isSubmitting: boolean;
    error: string | null;
    onClearError: () => void;
    onSubmit: (payload: { code?: string, recovery_code?: string, remember_device: boolean }) => void;
    onBack: () => void;
}

const formatRecoveryCode = (value: string) => {
    const characters = value.toLowerCase().replace(/[^a-z0-9]/g, '').slice(0, 10);
    return characters.length > 5 ? `${characters.slice(0, 5)}-${characters.slice(5)}` : characters;
};

export const TwoFactorChallenge = ({isSubmitting, error, onClearError, onSubmit, onBack}: TwoFactorChallengeProps) => {
    const [mode, setMode] = useState<ChallengeMode>('code');
    const [code, setCode] = useState('');
    const [recoveryCode, setRecoveryCode] = useState('');
    const [rememberDevice, setRememberDevice] = useState(false);
    const [showHelp, setShowHelp] = useState(false);

    const submitCode = (value: string = code) => {
        if (value.length === 6 && !isSubmitting) {
            onSubmit({code: value, remember_device: rememberDevice});
        }
    };

    const submitRecoveryCode = (event: FormEvent) => {
        event.preventDefault();
        if (recoveryCode.replace('-', '').length === 10) {
            onSubmit({recovery_code: recoveryCode, remember_device: rememberDevice});
        }
    };

    const switchMode = (nextMode: ChallengeMode) => {
        onClearError();
        setCode('');
        setRecoveryCode('');
        setMode(nextMode);
    };

    return (
        <div className={classes.challenge}>
            <header className={classes.challengeHeader}>
                <div className={classes.challengeIcon}>
                    {mode === 'code' ? <IconShieldLock size={26} stroke={1.75}/> : <IconKey size={26} stroke={1.75}/>}
                </div>
                <h2>{mode === 'code' ? t`Two-factor authentication` : t`Use a recovery code`}</h2>
                <p>
                    {mode === 'code'
                        ? t`Enter the 6-digit code from your authenticator app.`
                        : t`Enter one of the recovery codes you saved when you turned on two-factor authentication. Each code works once.`}
                </p>
            </header>

            <div className={classes.loginCard}>
                {mode === 'code' ? (
                    <form onSubmit={(event) => {
                        event.preventDefault();
                        submitCode();
                    }}>
                        <OneTimeCodeInput
                            label={t`Authentication code`}
                            value={code}
                            onChange={(value) => {
                                setCode(value);
                                onClearError();
                            }}
                            onComplete={submitCode}
                            error={error}
                            disabled={isSubmitting}
                            autoFocus
                            dataTestId="two-factor-login-code"
                        />
                        <Checkbox
                            mt="lg"
                            checked={rememberDevice}
                            onChange={(event) => setRememberDevice(event.currentTarget.checked)}
                            label={t`Trust this device for 30 days`}
                            data-testid="two-factor-remember-device"
                        />
                        <Button
                            color="secondary.5"
                            type="submit"
                            fullWidth
                            mt="lg"
                            loading={isSubmitting}
                            disabled={code.length !== 6}
                            data-testid="two-factor-login-submit"
                        >
                            {t`Verify`}
                        </Button>
                    </form>
                ) : (
                    <form onSubmit={submitRecoveryCode}>
                        <TextInput
                            label={t`Recovery code`}
                            placeholder="xxxxx-xxxxx"
                            value={recoveryCode}
                            onChange={(event) => {
                                setRecoveryCode(formatRecoveryCode(event.currentTarget.value));
                                onClearError();
                            }}
                            error={error}
                            autoFocus
                            autoComplete="off"
                            autoCapitalize="none"
                            autoCorrect="off"
                            spellCheck={false}
                            classNames={{input: classes.recoveryInput}}
                            data-testid="two-factor-login-recovery-code"
                        />
                        <Checkbox
                            mt="lg"
                            checked={rememberDevice}
                            onChange={(event) => setRememberDevice(event.currentTarget.checked)}
                            label={t`Trust this device for 30 days`}
                        />
                        <Button
                            color="secondary.5"
                            type="submit"
                            fullWidth
                            mt="lg"
                            loading={isSubmitting}
                            disabled={recoveryCode.replace('-', '').length !== 10}
                            data-testid="two-factor-login-submit"
                        >
                            {t`Verify recovery code`}
                        </Button>
                    </form>
                )}

                <div className={classes.challengeLinks}>
                    <UnstyledButton
                        className={classes.challengeLink}
                        onClick={() => switchMode(mode === 'code' ? 'recovery' : 'code')}
                        data-testid="two-factor-toggle-mode"
                    >
                        {mode === 'code' ? t`Use a recovery code instead` : t`Use your authenticator app instead`}
                    </UnstyledButton>
                    <UnstyledButton className={classes.challengeLink} onClick={() => setShowHelp(open => !open)}>
                        {t`Lost access?`}
                    </UnstyledButton>
                </div>

                {showHelp && (
                    <div className={classes.challengeHelp}>
                        <IconLifebuoy size={18}/>
                        <div>
                            <strong>{t`Can't use your authenticator app or recovery codes?`}</strong>
                            <p>
                                {t`Contact your account administrator or our support team. Once they've confirmed it's you, they can reset two-factor authentication so you can sign in with your password and set it up again.`}
                            </p>
                        </div>
                    </div>
                )}
            </div>

            <UnstyledButton className={classes.backToLogin} onClick={onBack}>
                <IconArrowLeft size={14}/>
                {t`Back to sign in`}
            </UnstyledButton>
        </div>
    );
};
