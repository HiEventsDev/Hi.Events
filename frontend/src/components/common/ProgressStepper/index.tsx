import {t} from "@lingui/macro";
import {IconCheck} from "@tabler/icons-react";
import classes from './ProgressStepper.module.scss';

interface Step {
    label: string;
    key: string;
}

interface ProgressStepperProps {
    isPaymentRequired: boolean;
    currentStep: 'details' | 'payment' | 'summary';
}

export const ProgressStepper = ({isPaymentRequired, currentStep}: ProgressStepperProps) => {
    const steps: Step[] = isPaymentRequired
        ? [
            {label: t`Details`, key: 'details'},
            {label: t`Payment`, key: 'payment'},
            {label: t`Summary`, key: 'summary'},
        ]
        : [
            {label: t`Details`, key: 'details'},
            {label: t`Summary`, key: 'summary'},
        ];

    const currentStepIndex = steps.findIndex(step => step.key === currentStep);

    return (
        <div className={classes.stepper}>
            {steps.map((step, index) => {
                const isCompleted = index < currentStepIndex;
                const isActive = index === currentStepIndex;

                return (
                    <div key={step.key} className={classes.stepContainer}>
                        <div className={classes.stepItem}>
                            <div
                                className={`${classes.circle} ${isActive || isCompleted ? classes.active : ''}`}
                            >
                                {isCompleted ? (
                                    <IconCheck size={14} stroke={3}/>
                                ) : (
                                    <span>{index + 1}</span>
                                )}
                            </div>
                            <span className={`${classes.label} ${isActive || isCompleted ? classes.activeLabel : ''}`}>
                                {step.label}
                            </span>
                        </div>
                        {index < steps.length - 1 && (
                            <div className={`${classes.connector} ${isCompleted ? classes.activeConnector : ''}`}/>
                        )}
                    </div>
                );
            })}
        </div>
    );
};
