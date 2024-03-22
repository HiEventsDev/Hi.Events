import {Modal} from "../../common/Modal";
import {t} from "@lingui/macro";
import {Account, IdParam} from "../../../types.ts";
import classes from "./ChooseAccountModal.module.scss";

interface ChooseAccountModalProps {
    onAccountChosen: (accountId: IdParam) => void;
    accounts: Account[];
}

export const ChooseAccountModal = ({onAccountChosen, accounts}: ChooseAccountModalProps) => {
    return (
        <Modal heading={t`Choose an account`} withCloseButton={false} onClose={()=>{}} opened>
            <p>{t`You have access to multiple accounts. Please choose one to continue.`}</p>
                {accounts.map(account => (
                    <div key={account.id} className={classes.accountRow} onClick={() => onAccountChosen(account.id)}>
                        {account.name}
                    </div>
                ))}
        </Modal>
    )
}
