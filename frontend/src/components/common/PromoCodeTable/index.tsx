import {t} from "@lingui/macro";
import {Event, PromoCode, PromoCodeDiscountType} from "../../../types.ts";
import {prettyDate, relativeDate} from "../../../utilites/dates.ts";
import {Badge, Button, Flex, Group, Menu, Table as MantineTable, Tooltip} from "@mantine/core";
import {Table, TableHead} from "../Table";
import {IconCheck, IconCircleOff, IconCopy, IconDotsVertical, IconSend, IconTrash} from "@tabler/icons-react";
import {Currency} from "../Currency";
import {useClipboard, useDisclosure} from "@mantine/hooks";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {EditPromoCodeModal} from "../../modals/EditPromoCodeModal";
import {useState} from "react";
import {NoResultsSplash} from "../NoResultsSplash";

interface PromoCodeTableProps {
    event: Event,
    promoCodes: PromoCode[],
}

export const PromoCodeTable = ({event, promoCodes}: PromoCodeTableProps) => {
    const [promoCodeId, setPromoCodeId] = useState<number | undefined>();
    const [editModalOpen, {open: openEditModal, close: closeEditModal}] = useDisclosure(false);

    const handleEditModal = (promoCodeId: number | undefined) => {
        setPromoCodeId(promoCodeId);
        openEditModal();
    };

    if (promoCodes.length === 0) {
        return <NoResultsSplash heading={t`No Promo Codes to show`}/>
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
                                    <Group wrap={'nowrap'} gap={0}>
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
                                                           }}>
                                                    {t`Copy URL`}
                                                </Menu.Item>
                                                <Menu.Divider/>

                                                <Menu.Label>{t`Danger zone`}</Menu.Label>
                                                <Menu.Item color="red" leftSection={<IconTrash
                                                    size={14}/>}>{t`Delete code`}</Menu.Item>
                                                <Menu.Item color="red" leftSection={<IconCircleOff
                                                    size={14}/>}>{t`Disable code`}</Menu.Item>
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
