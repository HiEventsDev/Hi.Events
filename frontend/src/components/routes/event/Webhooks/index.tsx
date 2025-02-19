import {PageTitle} from "../../../common/PageTitle";
import {t} from "@lingui/macro";
import {PageBody} from "../../../common/PageBody";
import {Anchor, Button, Group} from "@mantine/core"
import {IconPlus} from "@tabler/icons-react";
import {Card} from "../../../common/Card";
import {useDisclosure} from "@mantine/hooks";
import {WebhookTable} from "../../../common/WebhookTable";
import {useGetWebhooks} from "../../../../queries/useGetWebhooks.ts";
import {useParams} from "react-router";
import {CreateWebhookModal} from "../../../modals/CreateWebhookModal";

const Webhooks = () => {
    const {eventId} = useParams();
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);
    const webhooksQuery = useGetWebhooks(eventId);
    const webhooks = webhooksQuery.data?.data?.data;

    return (
        <PageBody>
            <PageTitle>
                {t`Webhooks`}
            </PageTitle>
            <Card>
                <Group justify="space-between">
                    <Button color={'green'} rightSection={<IconPlus/>} onClick={openCreateModal}>
                        {t`Add Webhook`}
                    </Button>
                    <div>
                        <Anchor>
                            {t`What is a webhook?`}
                        </Anchor>
                    </div>
                </Group>
            </Card>
            <div>
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
        </PageBody>
    );
}

export default Webhooks;
