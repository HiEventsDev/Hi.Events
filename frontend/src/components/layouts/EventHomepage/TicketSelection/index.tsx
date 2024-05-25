import {TicketWidget} from "../../TicketWidget";
import classes from './TicketSelection.module.scss';

export const TicketSelection = () => {
    return (
        <div className={classes.container}>
            <TicketWidget/>
        </div>
    );
}