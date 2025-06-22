import {useNavigate, useParams} from "react-router";
import {useGetMe} from "../../../../queries/useGetMe.ts";
import {useConfirmEmailChange} from "../../../../mutations/useConfirmEmailChange.ts";
import {Anchor, Button} from "@mantine/core";
import {Card} from "../../../common/Card";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {t, Trans} from "@lingui/macro";

const MessageCard = ({message, linkText, linkHref}: { message: string, linkText: string, linkHref: string }) => (
    <Card style={{marginTop: 'var(--hi-spacing-lg)'}}>
        {message} <Anchor href={linkHref}>{linkText}</Anchor>.
    </Card>
);

export const ConfirmEmailChange = () => {
    const {token} = useParams();
    const {data: userData, isFetched} = useGetMe();
    const navigate = useNavigate();
    const {mutate} = useConfirmEmailChange();

    if (!token) {
        return <MessageCard message={t`The link you clicked is invalid.`}
                            linkText={t`Go back to profile`}
                            linkHref="/manage/profile"
        />;
    }

    const confirmChange = () => {
        mutate({token: token, userId: userData?.id}, {
            onSuccess: () => {
                showSuccess(t`Successfully confirmed email change`);
                navigate('/manage/profile');
            },
            onError: () => {
                showError(t`Error confirming email change`);
            }
        });
    };

    if (isFetched && !userData?.pending_email) {
        return <MessageCard message={t`You have no pending email change.`}
                            linkText={t`Go back to profile`}
                            linkHref="/manage/profile"
        />;
    }

    return (
        <>
            {isFetched && (
                <>
                    <h2>{t`Confirm Email Change`}</h2>
                    <Card style={{marginTop: 'var(--hi-spacing-lg)'}}>
                        <Trans>You are changing your email to <b>{userData?.pending_email}</b>.</Trans>
                        <p>
                            <Button onClick={confirmChange}>
                                {t`Confirm Email Change`}
                            </Button>
                        </p>
                    </Card>
                </>
            )}
        </>
    );
};

export default ConfirmEmailChange;
