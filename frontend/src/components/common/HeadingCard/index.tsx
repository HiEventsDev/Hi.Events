import {Card} from "../Card";
import classes from './HeadingCard.module.scss';
import {Button} from "@mantine/core";
import {t} from "@lingui/macro";

interface HeadingCardProps {
    className?: string;
    heading: string;
    subHeading: string;
    buttonAction?: () => void;
    buttonText?: string;
    buttonDataTestId?: string;
}

export const HeadingCard = ({className = '', subHeading, heading, buttonAction, buttonText, buttonDataTestId}: HeadingCardProps) => {
    return (
        <Card className={`${className} ${classes.card}`}>
            <div>
                <div className={classes.heading}>
                    {heading}
                </div>
                <div className={classes.subHeading}>
                    {subHeading}
                </div>
            </div>
            {buttonAction && (
                <div className={classes.button}>
                    <Button size={'xs'} onClick={buttonAction} variant={'light'} data-testid={buttonDataTestId}>{buttonText || t`Add New`}</Button>
                </div>
            )}
        </Card>
    )
}