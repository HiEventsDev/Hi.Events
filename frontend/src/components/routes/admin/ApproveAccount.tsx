import {useEffect, useState} from "react";
import {useSearchParams} from "react-router";
import {api} from "../../../api/client.ts";
import {Card} from "../../common/Card";
import {t} from "@lingui/macro";

const ApproveAccount = () => {
    const [searchParams] = useSearchParams();
    const token = searchParams.get('token');
    const [status, setStatus] = useState<'loading' | 'success' | 'error' | 'already'>('loading');
    const [message, setMessage] = useState('');

    useEffect(() => {
        if (!token) {
            setStatus('error');
            setMessage(t`Missing approval token.`);
            return;
        }

        api.get(`/accounts/approve`, { params: { token } })
            .then((res) => {
                const msg = res.data?.message || t`Account approved successfully.`;
                if (msg.includes('already')) {
                    setStatus('already');
                } else {
                    setStatus('success');
                }
                setMessage(msg);
            })
            .catch((err) => {
                setStatus('error');
                setMessage(err.response?.data?.message || t`Failed to approve account.`);
            });
    }, [token]);

    return (
        <div style={{display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '60vh'}}>
            <Card style={{maxWidth: 500, textAlign: 'center', padding: '2rem'}}>
                {status === 'loading' && <p>{t`Approving account...`}</p>}
                {status === 'success' && (
                    <>
                        <h2 style={{color: 'green'}}>✓ {t`Account Approved`}</h2>
                        <p>{message}</p>
                    </>
                )}
                {status === 'already' && (
                    <>
                        <h2>ℹ️ {t`Already Approved`}</h2>
                        <p>{message}</p>
                    </>
                )}
                {status === 'error' && (
                    <>
                        <h2 style={{color: 'red'}}>✗ {t`Approval Failed`}</h2>
                        <p>{message}</p>
                    </>
                )}
            </Card>
        </div>
    );
};

export default ApproveAccount;
