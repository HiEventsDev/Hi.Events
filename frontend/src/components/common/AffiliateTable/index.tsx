import {t} from "@lingui/macro";
import {CreateButton} from "../CreateButton";
import {Button, VisuallyHidden} from '@mantine/core';
import {IdParam} from "../../../types.ts";
import {IconCopy, IconPencil, IconPlus, IconShare, IconTrash} from "@tabler/icons-react";
import {useClipboard, useDisclosure} from "@mantine/hooks";
import {useMemo, useState} from "react";
import {NoResultsSplash} from "../NoResultsSplash";
import {EditAffiliateModal} from "../../modals/EditAffiliateModal";
import {ShareModal} from "../../modals/ShareModal";
import {useDeleteAffiliate} from "../../../mutations/useDeleteAffiliate";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {confirmationDialog} from "../../../utilites/confirmationDialog.tsx";
import {useParams} from "react-router";
import {Affiliate} from "../../../api/affiliate.client.ts";
import {ActionMenu, RowActionsTarget} from "../ActionMenu";
import {TanStackTable, TanStackTableColumn} from "../TanStackTable";
import {CellCopy, CellText, EmptyCell, IdentityCell} from "../TanStackTable/cells.tsx";
import {sortableHeader, TableSort} from "../TanStackTable/sorting.tsx";
import {StatusBadge} from "../StatusBadge";
import {formatCurrency} from "../../../utilites/currency.ts";
import {relativeDate} from "../../../utilites/dates.ts";
import {eventHomepageUrl} from "../../../utilites/urlHelper.ts";
import {useGetEvent} from "../../../queries/useGetEvent.ts";

interface AffiliateTableProps {
    affiliates: Affiliate[];
    openCreateModal: () => void;
    isRefreshing?: boolean;
    sort?: TableSort;
}

export const AffiliateTable = ({affiliates, openCreateModal, isRefreshing, sort}: AffiliateTableProps) => {
    const [editModalOpen, {open: openEditModal, close: closeEditModal}] = useDisclosure(false);
    const [shareModalOpen, {open: openShareModal, close: closeShareModal}] = useDisclosure(false);
    const [selectedAffiliateId, setSelectedAffiliateId] = useState<IdParam>();
    const [selectedAffiliate, setSelectedAffiliate] = useState<Affiliate>();
    const deleteMutation = useDeleteAffiliate();
    const {eventId} = useParams();
    const copy = useClipboard()
    const {data: event} = useGetEvent(eventId);

    const handleDeleteAffiliate = (affiliateId: IdParam, eventId: IdParam) => {
        deleteMutation.mutate({affiliateId, eventId}, {
            onSuccess: () => {
                showSuccess(t`Affiliate deleted successfully`);
            },
            onError: (error: any) => {
                showError(error.message);
            }
        });
    }

    const copyToClipboard = (text: string) => {
        copy.copy(text)
        showSuccess(t`Copied to clipboard`);
    };

    const affiliateUrl = (affiliate: Affiliate) => eventHomepageUrl(event!) + `?aff=${affiliate.code}`;
    const openEdit = (affiliate: Affiliate) => {
        setSelectedAffiliateId(affiliate.id);
        openEditModal();
    };

    const columns = useMemo<TanStackTableColumn<Affiliate>[]>(() => [
        {
            id: 'affiliate',
            enableHiding: false,
            ...sortableHeader(t`Affiliate`, 'name', sort, 'asc'),
            meta: {label: t`Affiliate`, sticky: 'left'},
            cell: ({row: {original: affiliate}}) => (
                <IdentityCell
                    stacked
                    primary={<CellText strong title={affiliate.name}>{affiliate.name}</CellText>}
                    secondary={affiliate.email}
                />
            ),
        },
        {
            id: 'code',
            header: t`Code`,
            cell: ({row: {original: affiliate}}) => (
                <CellCopy value={affiliate.code}>
                    <CellText mono>{affiliate.code}</CellText>
                </CellCopy>
            ),
        },
        {
            id: 'status',
            header: t`Status`,
            cell: ({row: {original: affiliate}}) => (
                <StatusBadge tone={affiliate.status === 'ACTIVE' ? 'positive' : 'neutral'}>
                    {affiliate.status === 'ACTIVE' ? t`Active` : t`Inactive`}
                </StatusBadge>
            ),
        },
        {
            id: 'sales',
            ...sortableHeader(t`Sales`, 'total_sales', sort),
            meta: {label: t`Sales`, align: 'right'},
            cell: ({row: {original: affiliate}}) => <span>{affiliate.total_sales}</span>,
        },
        ...(affiliates.some(affiliate => affiliate.total_sales_gross !== undefined) ? [{
            id: 'revenue',
            ...sortableHeader(t`Revenue`, 'total_sales_gross', sort),
            meta: {label: t`Revenue`, align: 'right'},
            cell: ({row: {original: affiliate}}) => affiliate.total_sales_gross !== undefined && event
                ? <CellText strong>{formatCurrency(affiliate.total_sales_gross, event.currency)}</CellText>
                : <EmptyCell/>,
        } satisfies TanStackTableColumn<Affiliate>] : []),
        {
            id: 'created',
            ...sortableHeader(t`Created`, 'created_at', sort),
            meta: {label: t`Created`},
            cell: ({row: {original: affiliate}}) => relativeDate(affiliate.created_at),
        },
        {
            id: 'link',
            header: t`Link`,
            meta: {interactive: true},
            cell: ({row: {original: affiliate}}) => (
                <Button
                    size="compact-xs"
                    variant="default"
                    leftSection={<IconCopy size={12}/>}
                    onClick={() => copyToClipboard(affiliateUrl(affiliate))}
                >
                    {t`Copy URL`}
                </Button>
            ),
        },
        {
            id: 'actions',
            header: () => <VisuallyHidden>{t`Actions`}</VisuallyHidden>,
            enableHiding: false,
            meta: {sticky: 'right'},
            cell: ({row: {original: affiliate}}) => (
                <ActionMenu
                    target={<RowActionsTarget/>}
                    itemsGroups={[
                        {
                            label: t`Actions`,
                            items: [
                                {
                                    label: t`Edit Affiliate`,
                                    requiresEdit: true,
                                    icon: <IconPencil size={14}/>,
                                    onClick: () => openEdit(affiliate),
                                },
                                {
                                    label: t`Copy Code`,
                                    icon: <IconCopy size={14}/>,
                                    onClick: () => copyToClipboard(affiliate.code)
                                },
                                {
                                    label: t`Copy Affiliate Link`,
                                    icon: <IconCopy size={14}/>,
                                    onClick: () => copyToClipboard(affiliateUrl(affiliate))
                                },
                                {
                                    label: t`Share Affiliate Link`,
                                    icon: <IconShare size={14}/>,
                                    onClick: () => {
                                        setSelectedAffiliate(affiliate);
                                        openShareModal();
                                    }
                                },
                                {
                                    label: t`Delete Affiliate`,
                                    requiresEdit: true,
                                    icon: <IconTrash size={14}/>,
                                    color: "red",
                                    onClick: () => confirmationDialog(
                                        t`Are you sure you want to delete this affiliate? This action cannot be undone.`,
                                        () => handleDeleteAffiliate(affiliate.id, eventId!)
                                    )
                                }
                            ]
                        }
                    ]}
                />
            ),
        },
    ], [event, affiliates, sort?.sortBy, sort?.sortDirection]);

    if (affiliates.length === 0) {
        return (
            <NoResultsSplash
                heading={t`No Affiliates to show`}
                imageHref={'/blank-slate/affiliates.png'}
                subHeading={(
                    <>
                        <p>
                            {t`Affiliates help you track sales generated by partners and influencers. Create affiliate codes and share them to monitor performance.`}
                        </p>
                        <CreateButton
                            size={'xs'}
                            leftSection={<IconPlus/>}
                            color={'green'}
                            onClick={openCreateModal}
                        >
                            {t`Create Affiliate`}
                        </CreateButton>
                    </>
                )}
            />
        );
    }

    return (
        <>
            <TanStackTable rowLines={2} data={affiliates} columns={columns} noCard isRefreshing={isRefreshing}/>

            {editModalOpen && selectedAffiliateId && (
                <EditAffiliateModal
                    affiliateId={selectedAffiliateId}
                    onClose={closeEditModal}
                />
            )}

            {shareModalOpen && selectedAffiliate && event && (
                <ShareModal
                    opened={shareModalOpen}
                    onClose={closeShareModal}
                    url={eventHomepageUrl(event) + `?aff=${selectedAffiliate.code}`}
                    title={event.title}
                    modalTitle={t`Share Affiliate Link`}
                    shareText={t`Here is your affiliate link`}
                    event={event}
                />
            )}
        </>
    );
};
