import {PageTitle} from "../../../common/PageTitle";
import {t} from "@lingui/macro";
import {PageBody} from "../../../common/PageBody";
import {Tabs} from "@mantine/core";
import {useState} from "react";
import {IconMail, IconReceipt} from "@tabler/icons-react";
import {MarketingTab} from "./MarketingTab.tsx";
import {TransactionsTab} from "./TransactionsTab.tsx";

const MessageTracking = () => {
    const [activeTab, setActiveTab] = useState<string | null>('transactions');

    return (
        <PageBody>
            <PageTitle>
                {t`Message Tracking`}
            </PageTitle>

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
