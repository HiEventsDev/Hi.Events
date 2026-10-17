import {Loader, UnstyledButton} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconArrowLeft, IconBuildingCommunity, IconChevronRight} from "@tabler/icons-react";
import {useEffect, useRef} from "react";
import {Account, IdParam} from "../../../../types.ts";
import classes from "./Login.module.scss";

interface AccountChooserProps {
    accounts: Account[];
    pendingAccountId: IdParam | null;
    onChoose: (accountId: IdParam) => void;
    onBack: () => void;
}

export const AccountChooser = ({accounts, pendingAccountId, onChoose, onBack}: AccountChooserProps) => {
    const headingRef = useRef<HTMLHeadingElement>(null);

    useEffect(() => {
        headingRef.current?.focus();
    }, []);

    return (
        <div className={classes.challenge}>
            <header className={classes.challengeHeader}>
                <div className={classes.challengeIcon}>
                    <IconBuildingCommunity size={26} stroke={1.75}/>
                </div>
                <h2 ref={headingRef} tabIndex={-1}>{t`Choose an account`}</h2>
                <p>{t`You belong to more than one account. Choose where you'd like to go.`}</p>
            </header>

            <ul className={classes.accountList}>
                {accounts.map(account => {
                    const isPending = pendingAccountId === account.id;

                    return (
                        <li key={account.id}>
                            <button
                                type="button"
                                className={classes.accountOption}
                                onClick={() => onChoose(account.id)}
                                disabled={pendingAccountId !== null}
                                aria-busy={isPending}
                                data-testid="account-chooser-option"
                            >
                                <span className={classes.accountName}>{account.name}</span>
                                {isPending ? <Loader size={16} color="gray"/> : <IconChevronRight size={18} stroke={1.75}/>}
                            </button>
                        </li>
                    );
                })}
            </ul>

            <UnstyledButton className={classes.backToLogin} onClick={onBack}>
                <IconArrowLeft size={16}/>
                {t`Back to sign in`}
            </UnstyledButton>
        </div>
    );
};
