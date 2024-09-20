import {ProductWidget} from "../../ProductWidget";
import classes from './ProductSelection.module.scss';

export const ProductSelection = () => {
    return (
        <div className={classes.container}>
            <ProductWidget/>
        </div>
    );
}