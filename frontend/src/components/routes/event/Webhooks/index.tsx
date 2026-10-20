import {PageTitle} from "../../../common/PageTitle";
import {CreateButton} from "../../../common/CreateButton";
import {t} from "@lingui/macro";
import {PageBody} from "../../../common/PageBody";
import {Badge, Group} from "@mantine/core"
import {IconPlus} from "@tabler/icons-react";
import {Card} from "../../../common/Card";
import {useDisclosure} from "@mantine/hooks";
import {WebhookTable} from "../../../common/WebhookTable";
import {useGetWebhooks} from "../../../../queries/useGetWebhooks.ts";
import {useParams} from "react-router";
import {CreateWebhookModal} from "../../../modals/CreateWebhookModal";
import {TableSkeleton} from "../../../common/TableSkeleton";
import {RequirePermission} from "../../../common/WriteAccess";

const WebhookList = () => {
    const {eventId} = useParams();
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);
    const webhooksQuery = useGetWebhooks(eventId);
    const webhooks = webhooksQuery.data?.data?.data;

    const getWebhookCountText = () => {
        if (!webhooks) return t`Loading Webhooks`;
        if (webhooks.length === 0) return t`No Active Webhooks`;
        if (webhooks.length === 1) return t`1 Active Webhook`;
        return t`${webhooks.length} Active Webhooks`;
    };

    return (
        <>
            <Card>
                <Group justify="space-between">
                    <CreateButton color={'green'} rightSection={<IconPlus/>} onClick={openCreateModal}>
                        {t`Add Webhook`}
                    </CreateButton>
                    <Badge
                        variant="transparent"
                        size="lg"
                    >
                        {getWebhookCountText()}
                    </Badge>
                </Group>
            </Card>
            <div>
                <TableSkeleton isVisible={!webhooks}/>

                {webhooks && (<WebhookTable
                    webhooks={webhooks}
                    openCreateModal={openCreateModal}
                />)}
            </div>

            {createModalOpen && (
                <CreateWebhookModal
                    onClose={closeCreateModal}
                />
            )}
        </>
    );
}

const Webhooks = () => (
    <PageBody>
        <PageTitle
            subheading={t`Send real-time order and attendee data to your external systems.`}
        >
            {t`Webhooks`}
        </PageTitle>
        <RequirePermission permission="VIEW_FINANCIALS" imageHref="/blank-slate/webhooks.svg">
            <WebhookList/>
        </RequirePermission>
    </PageBody>
);

export default Webhooks;
