import {FormEvent, useState} from "react";
import {t} from "@lingui/macro";
import {Anchor, Button, Group, TextInput} from "@mantine/core";
import {IconMailCheck} from "@tabler/icons-react";
import {getTurnstileSiteKey, Turnstile} from "../../../../components/common/Turnstile";
import {useIsHydrated} from "../../../../hooks/useIsHydrated.ts";
import {getClientLocale} from "../../../../locales.ts";
import {useSubmitSignupForm} from "../../mutations/useCrmSignupFormMutations.ts";
import {CrmSignupFormContent} from "../../types.ts";
import {signupSubmissionError} from "../../utilites/publicErrors.ts";
import classes from "./SignupFormCard.module.scss";

export const SignupFormCard = ({form, publicId, variant = 'card', onSubmitted}: {
    form: CrmSignupFormContent,
    publicId?: string,
    variant?: 'card' | 'band' | 'plain',
    onSubmitted?: () => void,
}) => {
    const submit = useSubmitSignupForm();
    const [email, setEmail] = useState('');
    const [firstName, setFirstName] = useState('');
    const [lastName, setLastName] = useState('');
    const [website, setWebsite] = useState('');
    const [turnstileToken, setTurnstileToken] = useState('');
    const [turnstileResetKey, setTurnstileResetKey] = useState(0);
    const [submittedTo, setSubmittedTo] = useState<string | null>(null);
    const needsTurnstile = !!getTurnstileSiteKey();
    const isPreview = !publicId;
    const Root = isPreview ? 'div' : 'form';
    const Heading = variant === 'plain' ? 'h1' : 'h2';
    const isHydrated = useIsHydrated();

    const handleSubmit = (event: FormEvent) => {
        event.preventDefault();

        if (!publicId) {
            return;
        }

        submit.mutate({
            publicId,
            submission: {
                email: email.trim(),
                first_name: form.asks_for_name ? firstName.trim() || null : null,
                last_name: form.asks_for_name ? lastName.trim() || null : null,
                locale: getClientLocale(),
                turnstile_token: turnstileToken || null,
                website,
            },
        }, {
            onSuccess: () => {
                setSubmittedTo(email.trim());
                onSubmitted?.();
            },
            onError: () => setTurnstileResetKey(key => key + 1),
        });
    };

    if (submittedTo) {
        return (
            <div className={classes.root} data-variant={variant} role="status" data-testid="signup-form-check-inbox">
                <div className={classes.done}>
                    <IconMailCheck size={28} stroke={1.6} aria-hidden/>
                    <Heading className={classes.title}>{t`Check your inbox`}</Heading>
                    <p className={classes.description}>
                        {t`We sent a link to ${submittedTo}. Click it to confirm your subscription.`}
                    </p>
                </div>
            </div>
        );
    }

    return (
        <Root
            className={classes.root}
            data-variant={variant}
            {...(isPreview ? {inert: true} : {onSubmit: handleSubmit})}
        >
            <div className={classes.copy}>
                <Heading className={classes.title}>{form.title || t`Get updates`}</Heading>
                {form.description && <p className={classes.description}>{form.description}</p>}
            </div>
            <div className={classes.fields}>
                {form.asks_for_name && (
                    <Group grow gap="xs">
                        <TextInput
                            aria-label={t`First name`}
                            placeholder={t`First name`}
                            value={firstName}
                            onChange={event => setFirstName(event.currentTarget.value)}
                            maxLength={50}
                            disabled={!isHydrated}
                            autoComplete="given-name"
                        />
                        <TextInput
                            aria-label={t`Last name`}
                            placeholder={t`Last name`}
                            value={lastName}
                            onChange={event => setLastName(event.currentTarget.value)}
                            maxLength={50}
                            disabled={!isHydrated}
                            autoComplete="family-name"
                        />
                    </Group>
                )}
                <div className={classes.row}>
                    <TextInput
                        className={classes.email}
                        type="email"
                        required={!isPreview}
                        aria-label={t`Email address`}
                        placeholder={t`you@example.com`}
                        value={email}
                        onChange={event => setEmail(event.currentTarget.value)}
                        maxLength={254}
                        disabled={!isHydrated}
                        autoComplete="email"
                        data-testid="signup-form-email"
                    />
                    <Button
                        className={classes.submit}
                        type={isPreview ? 'button' : 'submit'}
                        loading={submit.isPending}
                        disabled={!isHydrated}
                        data-testid="signup-form-submit"
                    >
                        {form.button_label || t`Subscribe`}
                    </Button>
                </div>
                {!isPreview && (
                    <input
                        className={classes.honeypot}
                        type="text"
                        name="website"
                        tabIndex={-1}
                        autoComplete="off"
                        aria-hidden
                        value={website}
                        onChange={event => setWebsite(event.currentTarget.value)}
                    />
                )}
                {needsTurnstile && !isPreview && <Turnstile onToken={setTurnstileToken} resetKey={turnstileResetKey}/>}
                {submit.isError && (
                    <p className={classes.error} role="alert">
                        {signupSubmissionError(submit.error)}
                    </p>
                )}
                <p className={classes.fineprint}>
                    {t`We'll email you to confirm. Unsubscribe anytime.`}
                    {form.privacy_url && (
                        <>
                            {' '}
                            <Anchor href={form.privacy_url} target="_blank" rel="noreferrer" inherit>
                                {t`Privacy policy`}
                            </Anchor>
                        </>
                    )}
                </p>
            </div>
        </Root>
    );
};
