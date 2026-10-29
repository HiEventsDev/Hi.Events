import { t } from "@lingui/macro";
import {CreateButton} from "../../../common/CreateButton";
import { Badge, Group, Box } from "@mantine/core"
import { IconPlus } from "@tabler/icons-react";
import { Card } from "../../../common/Card";
import { useDisclosure } from "@mantine/hooks";
import { OrganizerWebhookTable } from "../../../common/OrganizerWebhookTable";
import { useGetOrganizerWebhooks } from "../../../../queries/useGetOrganizerWebhooks";
import { useParams } from "react-router";
import { CreateOrganizerWebhookModal } from "../../../modals/CreateOrganizerWebhookModal";
import { TableSkeleton } from "../../../common/TableSkeleton";
import { IdParam } from "../../../../types";
import { PageBody } from "../../../common/PageBody";
import { PageTitle } from "../../../common/PageTitle";
import { RequirePermission } from "../../../common/WriteAccess";

const WebhookList = () => {
    const { organizerId } = useParams();
    const [createModalOpen, { open: openCreateModal, close: closeCreateModal }] = useDisclosure(false);
    const webhooksQuery = useGetOrganizerWebhooks(organizerId as IdParam);
    const webhooks = webhooksQuery.data?.data?.data;

    const getWebhookCountText = () => {
        if (!webhooks) return t`Loading Webhooks`;
        if (webhooks.length === 0) return t`No Active Webhooks`;
        if (webhooks.length === 1) return t`1 Active Webhook`;
        return t`${webhooks.length} Active Webhooks`;
    };

    return (
        <Box>
            <Card>
                <Group justify="space-between">
                    <CreateButton color={'green'} rightSection={<IconPlus />} onClick={openCreateModal} data-testid="webhook-add-button">
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
                <TableSkeleton isVisible={!webhooks} />

                {webhooks && (<OrganizerWebhookTable
                    webhooks={webhooks}
                    openCreateModal={openCreateModal}
                />)}
            </div>

            {createModalOpen && (
                <CreateOrganizerWebhookModal
                    onClose={closeCreateModal}
                />
            )}
        </Box>
    );
};

export default function Webhooks() {
    return (
        <PageBody>
            <PageTitle>{t`Webhooks`}</PageTitle>
            <RequirePermission permission="VIEW_FINANCIALS" imageHref="/blank-slate/webhooks.svg">
                <WebhookList />
            </RequirePermission>
        </PageBody>
    );
}
