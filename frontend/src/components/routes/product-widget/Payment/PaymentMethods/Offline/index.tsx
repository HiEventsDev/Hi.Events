import {t} from "@lingui/macro";
import {Card} from "../../../../../common/Card";
import classes from "./OfflinePaymentMethod.module.scss";

export const OfflinePaymentMethod = ({isPayer}: { isPayer: boolean }) => (
    <Card>
        <div className={classes.title}>{t`How paying later works`}</div>
        <ol className={classes.steps} data-testid="pay-later-steps">
            <li>{t`Place your order. Your places are held and your tickets are issued, marked as unpaid.`}</li>
            <li>
                {isPayer
                    ? t`You'll see how to pay and the amount due on the next screen.`
                    : t`You'll see how to pay and the amount due on the next screen, and we'll email them to you.`}
            </li>
            <li>{t`The organizer confirms your order once your payment arrives.`}</li>
        </ol>
    </Card>
);
