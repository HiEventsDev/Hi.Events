import classes from './CardHeading.module.scss';

interface CardHeadingProps {
    heading: string;
    description: string;
}

export const HeadingWithDescription = ({heading, description}: CardHeadingProps) => {
    return (
        <div className={classes.wrapper}>
            <h2 className={classes.heading}>
                {heading}
            </h2>
            <p className={classes.description}>
                {description}
            </p>
        </div>
    );
}