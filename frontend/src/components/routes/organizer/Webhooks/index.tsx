import { t } from "@lingui/macro";
import { Badge, Button, Group, Box } from "@mantine/core"
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

export default function Webhooks() {
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
        <PageBody>
            <PageTitle>{t`Webhooks`}</PageTitle>
            <Box>
                <Card>
                    <Group justify="space-between">
                        <Button color={'green'} rightSection={<IconPlus />} onClick={openCreateModal}>
                            {t`Add Webhook`}
                        </Button>
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
        </PageBody>
    );
}
