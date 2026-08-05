import {Button} from "@mantine/core";
import {IconCheck, IconCircle, IconCircleCheck, IconX} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {Account, Event, EventType, Image, Organizer, User} from "../../../../types.ts";
import {BouncingEmoji} from "../../../common/BouncingEmoji";
import {useResendEmailConfirmation} from "../../../../mutations/useResendEmailConfirmation.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import classes from "./SetupChecklist.module.scss";

interface SetupChecklistProps {
    event: Event;
    organizer: Organizer | undefined;
    isStripeConnected: boolean;
    productCount: number;
    hasOccurrences: boolean;
    eventImages: Image[] | undefined;
    account: Account | undefined;
    me: User | undefined;
    onPublish: () => void;
    onConnectStripe: () => void;
    onAddTickets: () => void;
    onEditDetails: () => void;
    onSetupSchedule: () => void;
    onCustomizePage: () => void;
    onDismiss: () => void;
    isDismissed: boolean;
    showCongratsHeader?: boolean;
}

export const hasEventDetails = (event: Event): boolean => {
    const description = event.description?.trim() ?? '';

    return description.length > 0 && !!event.event_location;
};

type ActionStyle = 'primary' | 'secondary';

interface Step {
    key: string;
    title: string;
    helperIncomplete: string;
    helperComplete: string;
    complete: boolean;
    actionLabel?: string;
    actionStyle?: ActionStyle;
    onAction?: () => void;
    actionLoading?: boolean;
}

export const SetupChecklist = ({
                                   event,
                                   organizer,
                                   isStripeConnected,
                                   productCount,
                                   hasOccurrences,
                                   eventImages,
                                   account,
                                   me,
                                   onPublish,
                                   onConnectStripe,
                                   onAddTickets,
                                   onEditDetails,
                                   onSetupSchedule,
                                   onCustomizePage,
                                   onDismiss,
                                   isDismissed,
                                   showCongratsHeader = false,
                               }: SetupChecklistProps) => {
    const payoutsButtonLabel = organizer?.stripe_account_id
        ? t`Finish setup`
        : t`Connect bank`;

    const resendEmailConfirmation = useResendEmailConfirmation();
    const handleResendEmail = () => {
        if (!me?.id) return;
        resendEmailConfirmation.mutate({userId: me.id}, {
            onSuccess: () => showSuccess(t`Verification email sent. Check your inbox.`),
            onError: () => showError(t`Couldn't send verification email. Please try again.`),
        });
    };

    const hasCoverImage = (eventImages?.length ?? 0) > 0;
    const isEmailVerified = !!account?.is_account_email_confirmed;
    const isRecurring = event.type === EventType.RECURRING;
    const isSaasMode = !!account?.is_saas_mode_enabled;
    const hasTickets = productCount > 0;

    const steps: Step[] = [
        {
            key: 'tickets',
            title: t`Add tickets`,
            helperIncomplete: t`Set up the tickets you'll sell and their prices`,
            helperComplete: productCount === 1
                ? t`1 ticket type configured`
                : t`${productCount} ticket types configured`,
            complete: productCount > 0,
            actionLabel: t`Add tickets`,
            actionStyle: 'primary',
            onAction: onAddTickets,
        },
        ...(isRecurring ? [{
            key: 'schedule',
            title: t`Set up your schedule`,
            helperIncomplete: t`Add dates and times for your recurring event`,
            helperComplete: t`Schedule added`,
            complete: hasOccurrences,
            actionLabel: hasOccurrences ? t`Manage schedule` : t`Set up schedule`,
            actionStyle: 'primary',
            onAction: onSetupSchedule,
        } as Step] : []),
        {
            key: 'publish',
            title: t`Publish your event`,
            helperIncomplete: t`Make it visible so people can buy tickets`,
            helperComplete: t`Live`,
            complete: event.status === 'LIVE',
            actionLabel: t`Publish`,
            actionStyle:
                isRecurring
                    ? (hasOccurrences && hasTickets ? 'primary' : 'secondary')
                    : (!hasTickets ? 'secondary' : 'primary'),
            onAction: onPublish,
        },
        ...(isSaasMode ? [{
            key: 'payouts',
            title: t`Set up payouts`,
            helperIncomplete: t`Connect your bank to receive ticket sales straight to your account`,
            helperComplete: t`Bank account connected`,
            complete: isStripeConnected,
            actionLabel: payoutsButtonLabel,
            actionStyle: 'primary',
            onAction: onConnectStripe,
        } as Step] : []),
        {
            key: 'details',
            title: t`Add event details`,
            helperIncomplete: t`Add a description and venue so attendees know what to expect`,
            helperComplete: t`Description and venue added`,
            complete: hasEventDetails(event),
            actionLabel: t`Add details`,
            actionStyle: 'secondary',
            onAction: onEditDetails,
        },
        {
            key: 'customize',
            title: t`Customize your event page`,
            helperIncomplete: t`Add a cover image and theme to match your brand`,
            helperComplete: t`Cover image added`,
            complete: hasCoverImage,
            actionLabel: t`Customize page`,
            actionStyle: 'secondary',
            onAction: onCustomizePage,
        },
        ...(isSaasMode && me ? [{
            key: 'verify_email',
            title: t`Verify your email`,
            helperIncomplete: me?.email
                ? t`We sent a verification link to ${me.email}`
                : t`Verify your email so attendees can receive tickets`,
            helperComplete: t`Email verified`,
            complete: isEmailVerified,
            actionLabel: t`Resend email`,
            actionStyle: 'secondary',
            onAction: handleResendEmail,
            actionLoading: resendEmailConfirmation.isPending,
        } as Step] : []),
    ];

    const completedCount = steps.filter((s) => s.complete).length;
    const totalCount = steps.length;
    const allComplete = completedCount === totalCount;

    if (allComplete || isDismissed) {
        return null;
    }

    const sortedSteps = [...steps].sort((a, b) => Number(a.complete) - Number(b.complete));
    const progressPercent = (completedCount / totalCount) * 100;

    return (
        <div className={`${classes.card} ${showCongratsHeader ? classes.cardCongrats : ''}`}>
            <button
                type="button"
                className={`${classes.dismiss} ${showCongratsHeader ? classes.dismissCongrats : ''}`}
                onClick={onDismiss}
                aria-label={t`Dismiss setup checklist`}
            >
                <IconX size={showCongratsHeader ? 18 : 16}/>
            </button>

            {showCongratsHeader && (
                <div className={classes.congratsHero}>
                    <div className={classes.congratsEmoji}>
                        <BouncingEmoji emoji="🎉" size={72}/>
                    </div>
                    <div className={classes.congratsEyebrow}>{t`Event created`}</div>
                    <h2 className={classes.congratsTitle}>{event.title}</h2>
                    <p className={classes.congratsSubtitle}>
                        {t`A few quick steps and you're ready to start selling.`}
                    </p>
                </div>
            )}

            <div className={`${classes.header} ${showCongratsHeader ? classes.headerCompact : ''}`}>
                <div className={classes.headerLeft}>
                    {!showCongratsHeader && (
                        <div className={classes.title}>{t`Get your event ready`}</div>
                    )}
                    <div className={classes.subtitle}>
                        {t`${completedCount} of ${totalCount} steps complete`}
                    </div>
                </div>
                <div className={classes.headerRight}>
                    <div className={classes.progressTrack}>
                        <div
                            className={classes.progressFill}
                            style={{width: `${progressPercent}%`}}
                        />
                    </div>
                </div>
            </div>

            <div className={classes.body}>
                {sortedSteps.map((step) => (
                    <div key={step.key} className={classes.row}>
                        <div className={classes.rowLeft}>
                            {step.complete ? (
                                <IconCircleCheck size={18} className={classes.iconComplete}/>
                            ) : (
                                <IconCircle size={18} className={classes.iconIncomplete}/>
                            )}
                            <div className={classes.rowText}>
                                <span
                                    className={`${classes.rowTitle} ${step.complete ? classes.rowTitleComplete : classes.rowTitleIncomplete}`}
                                >
                                    {step.title}
                                </span>
                                <span
                                    className={`${classes.rowHelper} ${step.complete ? classes.rowHelperComplete : classes.rowHelperIncomplete}`}
                                >
                                    {step.complete ? step.helperComplete : step.helperIncomplete}
                                </span>
                            </div>
                        </div>
                        <div className={classes.rowRight}>
                            {step.complete ? (
                                <IconCheck size={15} className={classes.checkDone}/>
                            ) : step.actionLabel && step.onAction ? (
                                step.actionStyle === 'primary' ? (
                                    <Button
                                        size="xs"
                                        onClick={step.onAction}
                                        loading={step.actionLoading}
                                        className={classes.actionPrimary}
                                    >
                                        {step.actionLabel}
                                    </Button>
                                ) : (
                                    <Button
                                        size="xs"
                                        variant="default"
                                        onClick={step.onAction}
                                        loading={step.actionLoading}
                                        className={classes.actionSecondary}
                                    >
                                        {step.actionLabel}
                                    </Button>
                                )
                            ) : null}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
};

export default SetupChecklist;
