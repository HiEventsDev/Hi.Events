import classes from "./WidgetMessage.module.scss";

interface WidgetMessageProps {
    title: string;
    description?: string;
}

export const WidgetMessage = ({title, description}: WidgetMessageProps) => (
    <div className={classes.message} role="status">
        <p className={classes.title}>{title}</p>
        {description && <p className={classes.description}>{description}</p>}
    </div>
);
