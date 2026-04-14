import {PageTitle} from "../../../common/PageTitle";
import {t} from "@lingui/macro";
import {PageBody} from "../../../common/PageBody";
import {ActionIcon, Group, Tooltip, Tabs} from "@mantine/core";
import {useState, useCallback} from "react";
import {IconMail, IconReceipt, IconRefresh} from "@tabler/icons-react";
import {useQueryClient} from "@tanstack/react-query";
import {MarketingTab} from "./MarketingTab.tsx";
import {TransactionsTab} from "./TransactionsTab.tsx";
import {GET_OUTGOING_MESSAGES_QUERY_KEY} from "../../../../queries/useGetOutgoingMessages.ts";
import {GET_TRANSACTION_MESSAGES_QUERY_KEY} from "../../../../queries/useGetTransactionMessages.ts";

const MessageTracking = () => {
    const [activeTab, setActiveTab] = useState<string | null>('transactions');
    const [spinning, setSpinning] = useState(false);
    const queryClient = useQueryClient();

    const handleRefresh = useCallback(() => {
        const key = activeTab === 'transactions' ? GET_TRANSACTION_MESSAGES_QUERY_KEY : GET_OUTGOING_MESSAGES_QUERY_KEY;
        queryClient.invalidateQueries({queryKey: [key]});
        setSpinning(true);
        setTimeout(() => setSpinning(false), 600);
    }, [activeTab, queryClient]);

    return (
        <PageBody>
            <style>{`@keyframes refresh-nudge { 0% { transform: rotate(0deg); } 50% { transform: rotate(90deg); } 100% { transform: rotate(0deg); } }`}</style>
            <Group gap={4} align="center">
                <PageTitle>
                    {t`Message Tracking`}
                </PageTitle>
                <Tooltip label={t`Refresh`}>
                    <ActionIcon variant="subtle" color="gray" size="lg" onClick={handleRefresh} mb={10}>
                        <IconRefresh size={20} style={{
                            animation: spinning ? 'refresh-nudge 0.4s ease' : 'none',
                        }}/>
                    </ActionIcon>
                </Tooltip>
            </Group>

            <Tabs value={activeTab} onChange={setActiveTab}>
                <Tabs.List mb="md">
                    <Tabs.Tab value="transactions" leftSection={<IconReceipt size={16}/>}>
                        {t`Transactions`}
                    </Tabs.Tab>
                    <Tabs.Tab value="marketing" leftSection={<IconMail size={16}/>}>
                        {t`Announcements`}
                    </Tabs.Tab>
                </Tabs.List>

                <Tabs.Panel value="transactions">
                    <TransactionsTab/>
                </Tabs.Panel>

                <Tabs.Panel value="marketing">
                    <MarketingTab/>
                </Tabs.Panel>
            </Tabs>
        </PageBody>
    );
};

export default MessageTracking;
