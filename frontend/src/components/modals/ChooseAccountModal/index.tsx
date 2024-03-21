import {Modal} from "../../common/Modal";
import {t} from "@lingui/macro";
import {Account, IdParam} from "../../../types.ts";

interface ChooseAccountModalProps {
    onAccountChosen: (accountId: IdParam) => void;
    accounts: Account[];
}

export const ChooseAccountModal = ({onAccountChosen, accounts}: ChooseAccountModalProps) => {
    return (
        <Modal heading={t``} withCloseButton={false} onClose={()=>{}} opened>
                {accounts.map(account => (
                    <div key={account.id} onClick={() => onAccountChosen(account.id)}>
                        {account.name}
                    </div>
                ))}
        </Modal>
    )
}
