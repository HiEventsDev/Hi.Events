import {PageTitle} from "../../../common/PageTitle";
import {t} from "@lingui/macro";
import {PageBody} from "../../../common/PageBody";
import {Tabs} from "@mantine/core";
import {useState} from "react";
import {IconAlertTriangle, IconMail, IconReceipt} from "@tabler/icons-react";
import {DeliveryIssuesTab} from "./DeliveryIssuesTab.tsx";
import {MarketingTab} from "./MarketingTab.tsx";
import {TransactionsTab} from "./TransactionsTab.tsx";

const MessageTracking = () => {
    const [activeTab, setActiveTab] = useState<string | null>('delivery-issues');

    return (
        <PageBody>
            <PageTitle>
                {t`Message Tracking`}
            </PageTitle>

            <Tabs value={activeTab} onChange={setActiveTab}>
                <Tabs.List mb="md">
                    <Tabs.Tab value="delivery-issues" leftSection={<IconAlertTriangle size={16}/>}>
                        {t`Delivery Issues`}
                    </Tabs.Tab>
                    <Tabs.Tab value="marketing" leftSection={<IconMail size={16}/>}>
                        {t`Marketing`}
                    </Tabs.Tab>
                    <Tabs.Tab value="transactions" leftSection={<IconReceipt size={16}/>}>
                        {t`Transactions`}
                    </Tabs.Tab>
                </Tabs.List>

                <Tabs.Panel value="delivery-issues">
                    <DeliveryIssuesTab/>
                </Tabs.Panel>

                <Tabs.Panel value="marketing">
                    <MarketingTab/>
                </Tabs.Panel>

                <Tabs.Panel value="transactions">
                    <TransactionsTab/>
                </Tabs.Panel>
            </Tabs>
        </PageBody>
    );
};

export default MessageTracking;
