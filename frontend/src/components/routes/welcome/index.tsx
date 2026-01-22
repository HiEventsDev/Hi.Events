import {useGetMe} from "../../../queries/useGetMe.ts";
import {useGetOrganizers} from "../../../queries/useGetOrganizers.ts";
import {t, Trans} from "@lingui/macro";
import {Card} from "../../common/Card";
import {Button, Center, Container, PinInput, Stack, Text} from "@mantine/core";
import classes from "./Welcome.module.scss";
import {useForm} from "@mantine/form";
import {useDebouncedValue, useMediaQuery} from "@mantine/hooks";
import {useNavigate} from "react-router";
import {useEffect, useRef, useState} from "react";
import {OrganizerCreateForm} from "../../forms/OrganizerForm";
import {useConfirmEmailWithCode} from "../../../mutations/useConfirmEmailWithCode.ts";
import {useResendEmailConfirmation} from "../../../mutations/useResendEmailConfirmation.ts";
import {IconClock, IconMailCheck} from "@tabler/icons-react";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {getConfig} from "../../../utilites/config.ts";
import {trackEvent, AnalyticsEvents} from "../../../utilites/analytics.ts";

export const CreateOrganizer = ({progressInfo}: {
    progressInfo?: { currentStep: number, totalSteps: number, progressPercentage: number }
}) => {
    return (
        <div className={classes.stepContainer}>
            <div className={classes.stepHeader}>
                {progressInfo && (
                    <div className={classes.progressContainer}>
                        <div className={classes.progressBar}>
                            <div className={classes.progressFill}
                                 style={{width: `${progressInfo.progressPercentage}%`}}></div>
                        </div>
                    </div>
                )}
                <h2 className={classes.stepTitle}>
                    {t`Set up your organization`}
                </h2>
                <p className={classes.stepDescription}>
                    {t`Tell us about your organization. This information will be displayed on your event pages.`}
                </p>
            </div>
            <div className={classes.stepContent}>
                <OrganizerCreateForm/>
            </div>
        </div>
    );
}

const ConfirmVerificationPin = ({progressInfo}: {
    progressInfo: { currentStep: number, totalSteps: number, progressPercentage: number }
}) => {
    const {data: userData} = useGetMe();
    const confirmEmailMutation = useConfirmEmailWithCode();
    const resendMutation = useResendEmailConfirmation();
    const [resendCooldown, setResendCooldown] = useState(0);
    const [completedPin, setCompletedPin] = useState('');
    const isMobile = useMediaQuery('(max-width: 768px)');

    const form = useForm({
        initialValues: {
            pin: '',
        },
        validate: {
            pin: (value) => value.length !== 5 ? t`Please enter the 5-digit code` : null,
        }
    });

    // Debounce the completed pin value
    const [debouncedPin] = useDebouncedValue(completedPin, 800);

    // Auto-submit when debounced pin is complete
    useEffect(() => {
        if (debouncedPin.length === 5 && !confirmEmailMutation.isPending) {
            handleSubmit({pin: debouncedPin});
        }
    }, [debouncedPin]);

    useEffect(() => {
        if (resendCooldown > 0) {
            const timer = setTimeout(() => setResendCooldown(resendCooldown - 1), 1000);
            return () => clearTimeout(timer);
        }
    }, [resendCooldown]);

    const handleSubmit = (values: { pin: string }) => {
        confirmEmailMutation.mutate({
                userId: userData?.id || '',
                code: values.pin,
            }, {
                onSuccess: () => {
                    trackEvent(AnalyticsEvents.SIGNUP_COMPLETED);
                    showSuccess(t`Email verified successfully!`);
                    form.reset();
                    setCompletedPin('');
                },
                onError: (error) => {
                    showError(error.response?.data?.message || t`Failed to verify email`);
                    // Clear the pin on error so user can try again
                    form.reset();
                    setCompletedPin('');
                }
            }
        );
    }

    const handleResend = async () => {
        if (!userData?.id) return;

        try {
            await resendMutation.mutateAsync({userId: userData.id});
            showSuccess(t`A new verification code has been sent to your email`);
            setResendCooldown(30);
            form.reset();
        } catch (error: any) {
            if (error?.response?.status === 429) {
                const remainingSeconds = error.response.data?.message?.match(/\d+/)?.[0] || 30;
                setResendCooldown(parseInt(remainingSeconds));
                showError(error.response.data?.message || t`Please wait before requesting another code`);
            } else {
                showError(t`Failed to resend verification code`);
            }
        }
    }

    return (
        <div className={classes.stepContainer}>
            <div className={classes.stepHeader}>
                {progressInfo && (
                    <div className={classes.progressContainer}>
                        <div className={classes.progressBar}>
                            <div className={classes.progressFill}
                                 style={{width: `${progressInfo.progressPercentage}%`}}></div>
                        </div>
                    </div>
                )}
                <h2 className={classes.stepTitle}>
                    {t`Check your email`}
                </h2>
                <p className={classes.stepDescription}>
                    {t`We've sent a 5-digit verification code to:`}
                </p>
                <div className={classes.emailDisplay}>
                    {userData?.email}
                </div>
            </div>

            <div className={classes.stepContent}>
                <form onSubmit={form.onSubmit(handleSubmit)}>
                    <Stack gap={32}>
                        <Center>
                            <PinInput
                                {...form.getInputProps('pin')}
                                inputMode={'numeric'}
                                aria-label={t`Verification code`}
                                size={isMobile ? 'sm' : 'xl'}
                                length={5}
                                placeholder="•"
                                type="number"
                                disabled={confirmEmailMutation.isPending}
                                error={!!form.errors.pin}
                                className={classes.pinInput}
                                gap={isMobile ? 8 : "sm"}
                                onChange={(value) => {
                                    form.setFieldValue('pin', value);
                                    if (value.length === 5) {
                                        setCompletedPin(value);
                                    } else {
                                        setCompletedPin('');
                                    }
                                }}
                            />
                        </Center>

                        <Button
                            type={'submit'}
                            fullWidth
                            size="lg"
                            loading={confirmEmailMutation.isPending}
                            leftSection={<IconMailCheck size={20}/>}
                            className={classes.primaryButton}
                        >
                            {confirmEmailMutation.isPending ? t`Verifying...` : t`Verify Email`}
                        </Button>

                        <Center>
                            <Stack gap="xs" align="center">
                                <Text size="sm" c="dimmed">
                                    {t`Didn't receive the code?`}
                                </Text>
                                <Button
                                    variant="subtle"
                                    size="sm"
                                    onClick={handleResend}
                                    disabled={resendCooldown > 0 || resendMutation.isPending}
                                    loading={resendMutation.isPending}
                                    leftSection={resendCooldown > 0 ? <IconClock size={16}/> : null}
                                >
                                    {resendCooldown > 0
                                        ? t`Resend in ${resendCooldown}s`
                                        : t`Resend code`}
                                </Button>
                            </Stack>
                        </Center>

                        <Text size="xs" c="dimmed" ta="center" className={classes.helpText}>
                            {t`The code will expire in 10 minutes. Check your spam folder if you don't see the email.`}
                        </Text>
                    </Stack>
                </form>
            </div>
        </div>
    );
}

// Helper function to get progress information
const getProgressInfo = (requiresVerification: boolean, currentStep: 'verification' | 'organizer') => {
    const totalSteps = requiresVerification ? 2 : 1;
    let currentStepNumber = 1;

    if (requiresVerification) {
        if (currentStep === 'verification') currentStepNumber = 1;
        else if (currentStep === 'organizer') currentStepNumber = 2;
    } else {
        if (currentStep === 'organizer') currentStepNumber = 1;
    }

    const progressPercentage = (currentStepNumber / totalSteps) * 100;

    return {
        currentStep: currentStepNumber,
        totalSteps,
        progressPercentage
    };
};

const Welcome = () => {
    const {data: userData} = useGetMe();
    const organizersQuery = useGetOrganizers();
    const organizers = organizersQuery?.data?.data;
    const organizerExists = organizersQuery.isFetched && Number(organizers?.length) > 0;
    const hasTrackedSignup = useRef(false);
    const navigate = useNavigate();

    const requiresVerification = userData
        && userData.enforce_email_confirmation_during_registration
        && !userData.is_email_verified;

    useEffect(() => {
        if (!userData || hasTrackedSignup.current) {
            return;
        }
        // Only track if email verification was NEVER required for this account
        // Users who needed verification are tracked in ConfirmVerificationPin's onSuccess
        if (!userData.enforce_email_confirmation_during_registration) {
            hasTrackedSignup.current = true;
            trackEvent(AnalyticsEvents.SIGNUP_COMPLETED);
        }
    }, [userData]);

    useEffect(() => {
        if (!requiresVerification && organizerExists) {
            navigate('/manage/events', {replace: true});
        }
    }, [requiresVerification, organizerExists, navigate]);

    return (
        <div className={classes.welcomeContainer}>
            <Container size="sm" className={classes.welcomeContent}>
                <div className={classes.welcomeHeader}>
                    <div className={classes.logo}>
                        <img src={getConfig("VITE_APP_LOGO_LIGHT", "/logos/hi-events-text-dark.svg")} alt={`${getConfig("VITE_APP_NAME", "Hi.Events")} logo`} className={classes.logo}/>
                    </div>
                    <h1 className={classes.welcomeTitle}>
                        <Trans>
                            Welcome to {getConfig("VITE_APP_NAME", "Hi.Events")}, {userData?.first_name} 👋
                        </Trans>
                    </h1>
                </div>

                <Card className={classes.welcomeCard}>
                    {requiresVerification && <ConfirmVerificationPin
                        progressInfo={getProgressInfo(requiresVerification, 'verification')}/>}
                    {(!requiresVerification && !organizerExists) && <CreateOrganizer
                        progressInfo={getProgressInfo(requiresVerification, 'organizer')}/>}
                </Card>
            </Container>
        </div>
    )
}

export default Welcome;
