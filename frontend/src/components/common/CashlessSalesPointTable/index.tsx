import {t} from "@lingui/macro";
import {Badge, Button, Table as MantineTable, Text} from "@mantine/core";
import {IconArrowsExchange, IconCopy, IconPencil, IconPlus, IconQrcode, IconTrash} from "@tabler/icons-react";
import {useClipboard, useDisclosure} from "@mantine/hooks";
import {useState} from "react";
import {useNavigate, useParams} from "react-router";
import {CashlessSalesPoint, IdParam} from "../../../types.ts";
import {NoResultsSplash} from "../NoResultsSplash";
import {Table, TableHead} from "../Table";
import {ActionMenu} from "../ActionMenu";
import {ShareModal} from "../../modals/ShareModal";
import {EditCashlessSalesPointModal} from "../../modals/EditCashlessSalesPointModal";
import {useDeleteCashlessSalesPoint} from "../../../mutations/useDeleteCashlessSalesPoint.ts";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {confirmationDialog} from "../../../utilites/confirmationDialog.tsx";
import {formatCurrency} from "../../../utilites/currency.ts";
import {cashlessTransactionsPath} from "../../../utilites/cashlessLinks.ts";
import classes from "./CashlessSalesPointTable.module.scss";

interface CashlessSalesPointTableProps {
    salesPoints: CashlessSalesPoint[];
    currency: string;
    openCreateModal: () => void;
}

const salesPointUrl = (shortId: string) =>
    typeof window === 'undefined' ? '' : `${window.location.origin}/cashless/pos/${shortId}`;

export const CashlessSalesPointTable = ({salesPoints, currency, openCreateModal}: CashlessSalesPointTableProps) => {
    const {eventId} = useParams();
    const navigate = useNavigate();
    const copy = useClipboard();
    const [selectedSalesPoint, setSelectedSalesPoint] = useState<CashlessSalesPoint>();
    const [editOpen, {open: openEdit, close: closeEdit}] = useDisclosure(false);
    const [shareOpen, {open: openShare, close: closeShare}] = useDisclosure(false);
    const deleteMutation = useDeleteCashlessSalesPoint();

    const handleDelete = (salesPointId: IdParam) => {
        confirmationDialog(
            t`Delete this sales point? Its link will stop working immediately. Past transactions are kept.`,
            () => {
                deleteMutation.mutate({eventId, salesPointId}, {
                    onSuccess: () => showSuccess(t`Sales point deleted`),
                    onError: (error: any) => showError(
                        error?.response?.data?.message || t`Failed to delete this sales point`
                    ),
                });
            },
        );
    };

    if (salesPoints.length === 0) {
        return (
            <NoResultsSplash
                imageHref={"/blank-slate/check-in-lists.svg"}
                heading={t`No sales points yet`}
                subHeading={(
                    <>
                        <p>
                            {t`A sales point is a bar or stand. Give your team its link and they can scan tickets to take payment.`}
                        </p>
                        <Button
                            size="xs"
                            leftSection={<IconPlus/>}
                            color="green"
                            onClick={openCreateModal}
                        >
                            {t`Create sales point`}
                        </Button>
                    </>
                )}
            />
        );
    }

    return (
        <>
            <Table>
                <TableHead>
                    <MantineTable.Tr>
                        <MantineTable.Th>{t`Name`}</MantineTable.Th>
                        <MantineTable.Th>{t`Products`}</MantineTable.Th>
                        <MantineTable.Th>{t`Sales`}</MantineTable.Th>
                        <MantineTable.Th>{t`Security`}</MantineTable.Th>
                        <MantineTable.Th/>
                    </MantineTable.Tr>
                </TableHead>
                <MantineTable.Tbody>
                    {salesPoints.map((salesPoint) => (
                        <MantineTable.Tr key={salesPoint.id}>
                            <MantineTable.Td>
                                <Text fw={500}>{salesPoint.name}</Text>
                                <Text size="xs" c="dimmed" className={classes.link}>
                                    {salesPointUrl(salesPoint.short_id)}
                                </Text>
                            </MantineTable.Td>
                            <MantineTable.Td>
                                <Text size="sm">
                                    {salesPoint.products?.map((product) => product.title).join(', ')}
                                </Text>
                            </MantineTable.Td>
                            <MantineTable.Td>
                                <Text fw={600}>{formatCurrency(salesPoint.sales_total ?? 0, currency)}</Text>
                                <Text size="xs" c="dimmed">
                                    {t`${salesPoint.transaction_count ?? 0} transactions`}
                                </Text>
                            </MantineTable.Td>
                            <MantineTable.Td>
                                <Badge variant="light" color={salesPoint.has_access_pin ? 'green' : 'gray'}>
                                    {salesPoint.has_access_pin ? t`PIN protected` : t`Link only`}
                                </Badge>
                            </MantineTable.Td>
                            <MantineTable.Td>
                                <ActionMenu
                                    itemsGroups={[{
                                        label: t`Manage`,
                                        items: [
                                            {
                                                label: t`View transactions`,
                                                icon: <IconArrowsExchange size={14}/>,
                                                onClick: () => navigate(cashlessTransactionsPath(eventId, {salesPointId: salesPoint.id})),
                                                dataTestId: 'cashless-sales-point-transactions-menu-item',
                                            },
                                            {
                                                label: t`Share with staff`,
                                                icon: <IconQrcode size={14}/>,
                                                onClick: () => {
                                                    setSelectedSalesPoint(salesPoint);
                                                    openShare();
                                                },
                                            },
                                            {
                                                label: t`Copy link`,
                                                icon: <IconCopy size={14}/>,
                                                onClick: () => {
                                                    copy.copy(salesPointUrl(salesPoint.short_id));
                                                    showSuccess(t`Copied to clipboard`);
                                                },
                                            },
                                            {
                                                label: t`Edit`,
                                                icon: <IconPencil size={14}/>,
                                                onClick: () => {
                                                    setSelectedSalesPoint(salesPoint);
                                                    openEdit();
                                                },
                                                dataTestId: 'cashless-sales-point-edit-menu-item',
                                            },
                                            {
                                                label: t`Delete`,
                                                icon: <IconTrash size={14}/>,
                                                color: 'red',
                                                onClick: () => handleDelete(salesPoint.id),
                                            },
                                        ],
                                    }]}
                                />
                            </MantineTable.Td>
                        </MantineTable.Tr>
                    ))}
                </MantineTable.Tbody>
            </Table>

            {editOpen && selectedSalesPoint && (
                <EditCashlessSalesPointModal salesPoint={selectedSalesPoint} onClose={closeEdit}/>
            )}

            {shareOpen && selectedSalesPoint && (
                <ShareModal
                    opened
                    onClose={closeShare}
                    url={salesPointUrl(selectedSalesPoint.short_id)}
                    title={selectedSalesPoint.name}
                    modalTitle={t`Share this sales point with your team`}
                />
            )}
        </>
    );
};
