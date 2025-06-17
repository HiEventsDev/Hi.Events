import {useGetApiKeys, GET_API_KEYS_QUERY_KEY} from "../../../../../../queries/useGetApiKeys.ts";
import {useRevokeApiKey} from "../../../../../../mutations/useRevokeApiKey.ts";
import {ActionIcon, Badge, Button, CopyButton, HoverCard, Table, Text, TextInput, Tooltip} from "@mantine/core";
import classes from "./ApiKeys.module.scss";
import {IconCheck, IconCopy} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {Card} from "../../../../../common/Card";
import {HeadingCard} from "../../../../../common/HeadingCard";
import {relativeDate} from "../../../../../../utilites/dates.ts";
import {useDisclosure} from "@mantine/hooks";
import {CreateApiKeyModal} from "../../../../../modals/CreateApiKeyModal";
import {NewApiKey} from "../../../../../../types.ts";
import {showError, showSuccess} from "../../../../../../utilites/notifications.tsx";
import {LoadingMask} from "../../../../../common/LoadingMask";
import {confirmationDialog} from "../../../../../../utilites/confirmationDialog.tsx";
import {useQueryClient} from "@tanstack/react-query";
import {modals} from "@mantine/modals";

// TODO: Translations
const ApiKeys = () => {
    const apiKeysQuery = useGetApiKeys();
    const queryClient = useQueryClient();
    const revokeApiKeyMutation = useRevokeApiKey();
    const apiKeys = apiKeysQuery.data;
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);


    const onCompleted = (apiKey: NewApiKey) => {
        modals.open({
            title: 'API Key Created',
            children: (
                <>
                    <Text>Your new API key can be copied below. This is the only time it will be displayed.</Text>
                    <TextInput value={apiKey.plainTextToken} readOnly autoComplete="off" rightSection={
                        <CopyButton value={apiKey.plainTextToken} timeout={2000} data-autofocus>
                          {({ copied, copy }) => (
                            <Tooltip label={copied ? 'Copied' : 'Copy'} withArrow position="right">
                              <ActionIcon color={copied ? 'teal' : 'gray'} variant="subtle" onClick={copy}>
                                {copied ? <IconCheck size={16} /> : <IconCopy size={16} />}
                              </ActionIcon>
                            </Tooltip>
                          )}
                        </CopyButton>
                    }/>
                    <Button fullWidth onClick={modals.closeAll} mt="md">
                        {`Close`}
                    </Button>
                </>
                ),
        });
    }

    const onDelete = (id: IdParam) => {
        confirmationDialog(`Are you sure you want to revoke this API key?`, () => {
            revokeApiKeyMutation.mutate({
                tokenId: id
            }, {
                onSuccess: () => {
                    showSuccess(`API key deleted`);
                    queryClient.invalidateQueries({queryKey: [GET_API_KEYS_QUERY_KEY]});
                },
                onError: (error) => {
                    // eslint-disable-next-line @typescript-eslint/ban-ts-comment
                    // @ts-ignore
                    showError(error?.response?.data?.message || `Failed to delete API key. Please try again.`);
                }
            });
        });
    }

    const rows = apiKeys?.map((apiKey) => {
        const badgeText = apiKey.abilities.includes('*') ?
                "All Abilities" :
                `${apiKey.abilities.length} ${apiKey.abilities.length == 1 ? `Ability` : `Abilities`}`;
        const badgeTooltip = apiKey.abilities.includes('*') ?
                "This API Key can perform all actions." :
                apiKey.abilities.join(', ');
        const expirationDateTime = apiKey.expires_at == null ?
                'Never' :
                relativeDate(apiKey.expires_at);

        const isExpiryDateInPast = apiKey.expires_at != null && new Date() > new Date(apiKey.expires_at);

        return (
            <Table.Tr key={apiKey.id}>
                <Table.Td>
                    {apiKey.name}
                </Table.Td>

                <Table.Td>
                    <HoverCard width={280} shadow="md" position="right">
                        <HoverCard.Target>
                            <Badge className={classes.abilitiesBadge}>{badgeText}</Badge>
                        </HoverCard.Target>
                        <HoverCard.Dropdown>
                            <Text size="sm">
                                {badgeTooltip}
                            </Text>
                        </HoverCard.Dropdown>
                    </HoverCard>
                </Table.Td>

                <Table.Td>
                    <Text color={isExpiryDateInPast ? "red" : "black"}>{expirationDateTime}</Text>
                </Table.Td>

                <Table.Td>
                    <Button color={'red'} onClick={() => onDelete(apiKey.id)}>
                        {`Revoke Key`}
                    </Button>
                </Table.Td>
            </Table.Tr>
        );
    });

    return (
        <>
            <HeadingCard
                heading={`API Key Management`}
                subHeading={`Manage your generated API keys`}
                buttonText={`Generate API Key`}
                buttonAction={openCreateModal}
            />

            <Card style={{padding: 0, position: 'relative'}}>
                <LoadingMask/>
                <Table.ScrollContainer className={classes.table} minWidth={800}>
                    <Table verticalSpacing="sm">
                        <Table.Thead>
                            <Table.Tr>
                                <Table.Th>{t`Name`}</Table.Th>
                                <Table.Th>{`Abilities`}</Table.Th>
                                <Table.Th>{`Expiry Date`}</Table.Th>
                                <Table.Th>{`Options`}</Table.Th>
                            </Table.Tr>
                        </Table.Thead>
                        <Table.Tbody>{rows}</Table.Tbody>
                    </Table>
                </Table.ScrollContainer>
            </Card>
            {createModalOpen && <CreateApiKeyModal onCompleted={onCompleted} onClose={closeCreateModal}/>}
        </>
    );
}

export default ApiKeys;