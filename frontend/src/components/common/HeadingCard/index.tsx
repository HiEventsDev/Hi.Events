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
}

export const HeadingCard = ({className = '', subHeading, heading, buttonAction, buttonText}: HeadingCardProps) => {
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
                    <Button size={'xs'} onClick={buttonAction} variant={'light'}>{buttonText || t`Add New`}</Button>
                </div>
            )}
        </Card>
    )
}