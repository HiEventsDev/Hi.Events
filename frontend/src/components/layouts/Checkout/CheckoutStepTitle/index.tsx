import classes from "./CheckoutStepTitle.module.scss";

interface CheckoutStepTitleProps {
    title: string;
    subtitle?: string;
}

export const CheckoutStepTitle = ({title, subtitle}: CheckoutStepTitleProps) => (
    <div className={classes.stepTitleBlock}>
        <h2 className={classes.title}>{title}</h2>
        {subtitle && <p className={classes.subtitle}>{subtitle}</p>}
    </div>
);
