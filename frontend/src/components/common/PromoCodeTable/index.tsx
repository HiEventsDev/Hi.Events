import {t} from "@lingui/macro";
import {Event, PromoCode, PromoCodeDiscountType} from "../../../types.ts";
import {prettyDate, relativeDate} from "../../../utilites/dates.ts";
import {Badge, Button, Flex, Group, Menu, Table as MantineTable, Tooltip} from "@mantine/core";
import {Table, TableHead} from "../Table";
import {IconCheck, IconCopy, IconDotsVertical, IconPlus, IconSend, IconTrash} from "@tabler/icons-react";
import {Currency} from "../Currency";
import {useClipboard, useDisclosure} from "@mantine/hooks";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {EditPromoCodeModal} from "../../modals/EditPromoCodeModal";
import {useState} from "react";
import {NoResultsSplash} from "../NoResultsSplash";
import {confirmationDialog} from "../../../utilites/confirmationDialog.tsx";
import {useDeletePromoCode} from "../../../mutations/useDeletePromoCode.ts";
import {eventHomepageUrl} from "../../../utilites/urlHelper.ts";

interface PromoCodeTableProps {
    event: Event,
    promoCodes: PromoCode[],
    openCreateModal: () => void,
}

export const PromoCodeTable = ({event, promoCodes, openCreateModal}: PromoCodeTableProps) => {
    const [promoCodeId, setPromoCodeId] = useState<number | undefined>();
    const [editModalOpen, {open: openEditModal, close: closeEditModal}] = useDisclosure(false);
    const deleteMutation = useDeletePromoCode();
    const clipboard = useClipboard({ timeout: 500 });

    const handleEditModal = (promoCodeId: number | undefined) => {
        setPromoCodeId(promoCodeId);
        openEditModal();
    };

    const handleDeleteCode = (promoCodeId: number) => {
        confirmationDialog(
            t`Are you sure you want to delete this promo code?`,
            () => {
                deleteMutation.mutate({eventId: event.id, promoCodeId});
            }, {confirm: t`Delete`, cancel: t`Cancel`}
        );
    }

    if (promoCodes.length === 0) {
        return <NoResultsSplash
            heading={t`No Promo Codes to show`}
            imageHref={'/blank-slate/promo-codes.svg'}
            subHeading={(
                <>
                    <p>
                        {t`Promo codes can be used to offer discounts, presale access, or provide special access to your event.`}
                    </p>
                    <Button
                        size={'xs'}
                        leftSection={<IconPlus/>}
                        color={'green'}
                        onClick={() => openCreateModal()}>{t`Create a Promo Code`}
                    </Button>
                </>
            )}
        />
    }

    return (
        <>
            <Table>
                <TableHead>
                    <MantineTable.Tr>
                        <MantineTable.Th>{t`Code`}</MantineTable.Th>
                        <MantineTable.Th>{t`Discount`}</MantineTable.Th>
                        <MantineTable.Th>{t`Times used`}</MantineTable.Th>
                        <MantineTable.Th>{t`Tickets`}</MantineTable.Th>
                        <MantineTable.Th>{t`Expires`}</MantineTable.Th>
                        <MantineTable.Th></MantineTable.Th>
                    </MantineTable.Tr>
                </TableHead>

                <MantineTable.Tbody>
                    {promoCodes?.map(code => {
                        const Discount = () => {
                            if (code?.discount === 0) {
                                return <>{t`None`}</>;
                            }

                            if (code.discount_type === PromoCodeDiscountType.Fixed) {
                                return <Currency currency={event.currency} price={code.discount}/>;
                            }

                            return <>{code.discount}%</>;
                        };

                        const CopyCodeBadge = () => {
                            const clipboard = useClipboard({timeout: 500});

                            return (
                                <Badge
                                    variant={'outline'}
                                    title={t`Click to copy`}
                                    style={{cursor: 'pointer', alignItems: 'center'}}
                                    rightSection={(<Flex>
                                        {clipboard.copied ? <IconCheck color={'green'} size={'12'}/> :
                                            <IconCopy size={'12'}/>}
                                    </Flex>)}
                                    onClick={() => {
                                        clipboard.copy(code.code.toUpperCase());
                                        showSuccess(`${code?.code.toUpperCase()} ${t`copied to clipboard`}`)
                                    }}>
                                    {code.code.toUpperCase()}
                                </Badge>
                            )
                        }

                        return (
                            <MantineTable.Tr>
                                <MantineTable.Td>
                                    <CopyCodeBadge/>
                                </MantineTable.Td>
                                <MantineTable.Td>
                                    <Badge color={'cyan'} variant={'light'}>
                                        <Discount/>
                                    </Badge>
                                </MantineTable.Td>
                                <MantineTable.Td>
                                    {code.order_usage_count} / {code.max_allowed_usages
                                    ? code.max_allowed_usages
                                    : <span title={t`Unlimited usages allowed`}>∞</span>}
                                </MantineTable.Td>
                                <MantineTable.Td>
                                    <div style={{cursor: 'pointer'}}>
                                        {code.applicable_ticket_ids?.length === 0 && (
                                            <Badge variant={'light'} color={'pink'}>{t`All Tickets`}</Badge>
                                        )}

                                        {Number(code.applicable_ticket_ids?.length) > 0 && (
                                            <Tooltip label={
                                                event?.tickets?.filter(ticket =>
                                                    code.applicable_ticket_ids?.map(Number)?.includes(Number(ticket.id)))
                                                    .map(ticket => {
                                                        return (
                                                            <>
                                                                {ticket.title}
                                                                <br/>
                                                            </>
                                                        );
                                                    })}>
                                                <Badge
                                                    variant={'light'}
                                                    color={'pink'}>{code.applicable_ticket_ids?.length} {t`Ticket(s)`}</Badge>
                                            </Tooltip>
                                        )}
                                    </div>
                                </MantineTable.Td>
                                <MantineTable.Td>{code.expiry_date ?
                                    <Tooltip label={prettyDate(String(code.expiry_date), event.timezone)}>
                                    <span>
                                        {relativeDate(String(code.expiry_date))}
                                    </span>
                                    </Tooltip> : t`Never`}</MantineTable.Td>
                                <MantineTable.Td>
                                    <Group wrap={'nowrap'} gap={0} justify={'flex-end'}>
                                        <Menu shadow="md" width={200}>
                                            <Menu.Target>
                                                <Button size={'xs'} variant={'transparent'}><IconDotsVertical/></Button>
                                            </Menu.Target>

                                            <Menu.Dropdown>
                                                <Menu.Label>{t`Manage`}</Menu.Label>
                                                <Menu.Item leftSection={<IconSend size={14}/>}
                                                           onClick={() => handleEditModal(code?.id)}>
                                                    {t`Edit Code`}
                                                </Menu.Item>
                                                <Menu.Item leftSection={<IconCopy size={14}/>}
                                                           onClick={() => {
                                                                clipboard.copy(eventHomepageUrl(event) + `?promo_code=${code?.code}`);
                                                                showSuccess(t`URL copied to clipboard`)
                                                           }}>
                                                    {t`Copy URL`}
                                                </Menu.Item>
                                                <Menu.Divider/>

                                                <Menu.Label>{t`Danger zone`}</Menu.Label>
                                                <Menu.Item color="red"
                                                           onClick={() => handleDeleteCode(code?.id as number)}
                                                           leftSection={<IconTrash size={14}/>}>
                                                    {t`Delete code`}
                                                </Menu.Item>

                                            </Menu.Dropdown>
                                        </Menu>
                                    </Group>
                                </MantineTable.Td>
                            </MantineTable.Tr>
                        )
                    })}
                </MantineTable.Tbody>
            </Table>

            {(promoCodeId && editModalOpen) &&
                <EditPromoCodeModal promoCodeId={promoCodeId} onClose={closeEditModal}/>}
        </>
    )
}
