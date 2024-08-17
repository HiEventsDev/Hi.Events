import {Badge, Button, Group, Menu} from "@mantine/core";
import classes from './TaxAndFeeList.module.scss';
import {useGetTaxesAndFees} from "../../../queries/useGetTaxesAndFees.ts";
import {TaxAndFee, TaxAndFeeCalculationType} from "../../../types.ts";
import {formatCurrency} from "../../../utilites/currency.ts";
import {useGetAccount} from "../../../queries/useGetAccount.ts";
import {IconDotsVertical, IconPencil, IconTrash} from "@tabler/icons-react";
import {useDisclosure} from "@mantine/hooks";
import {EditTaxOrFeeModal} from "../../modals/EditTaxOrFeeModal";
import {useState} from "react";
import {useDeleteTaxOrFee} from "../../../mutations/useDeleteTaxOrFee.ts";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";

export const TaxAndFeeList = () => {
    const {data: taxesAndFees, isFetched} = useGetTaxesAndFees();
    const {data: account} = useGetAccount();
    const [editModalOpen, {open: openEditModal, close: closeEditModal}] = useDisclosure(false);
    const [selectedTaxOrFee, setSelectedTaxOrFee] = useState<TaxAndFee>();
    const deleteMutation = useDeleteTaxOrFee();

    if (!isFetched || !taxesAndFees) {
        return <></>
    }

    const handleEdit = (taxAndFee: TaxAndFee) => {
        setSelectedTaxOrFee(taxAndFee);
        openEditModal();
    }

    const handleDelete = (taxAndFee: TaxAndFee) => {
        deleteMutation.mutate({
            taxAndFeeId: taxAndFee.id,
            accountId: taxAndFee.account_id,
        }, {
            onSuccess: () => showSuccess(t`Tax or Fee deleted successfully`),
            onError: (error) => {
                console.error(error);
                showError(t`Something went wrong while deleting the Tax or Fee`);
            },
        });
    }

    const TaxList = () => {
        return <div className={classes.taxes}>
            {taxesAndFees.data.map((tax) => (
                <div key={tax.id} className={classes.taxBlock}>
                    <div className={classes.header}>
                        <div className={classes.type}>
                            {tax.type.toLocaleLowerCase()}
                        </div>
                        <div className={classes.action}>
                            <Group wrap={'nowrap'} gap={0}>
                                <Menu shadow="md" width={200}>
                                    <Menu.Target>
                                        <Button size={'compact-xs'} variant={'transparent'}><IconDotsVertical
                                            size={14}/></Button>
                                    </Menu.Target>

                                    <Menu.Dropdown>
                                        <Menu.Item
                                            leftSection={<IconPencil size={14}/>}
                                            onClick={() => handleEdit(tax)}
                                        >
                                            {t`Edit`}
                                        </Menu.Item>

                                        <Menu.Item
                                            color={'red'}
                                            leftSection={<IconTrash size={14}/>}
                                            onClick={() => handleDelete(tax)}
                                        >
                                            {t`Delete`}
                                        </Menu.Item>
                                    </Menu.Dropdown>
                                </Menu>
                            </Group>
                        </div>

                    </div>
                    <div className={classes.body}>
                        <div className={classes.name}>
                            {tax.name}
                        </div>
                        <div className={classes.value}>
                            <Badge variant={'light'}>
                                {tax.calculation_type === TaxAndFeeCalculationType.Percentage
                                    ? tax.rate + '%'
                                    : formatCurrency(Number(tax.rate), account?.currency_code)}
                            </Badge>
                        </div>
                    </div>
                </div>
            ))}
        </div>;
    }

    const NoTaxes = () => {
        return (
            <div className={classes.noTaxes}>
                <p>{t`No Taxes or Fees have been added.`} </p>
            </div>
        )
    }

    return (
        <>
            {taxesAndFees.data.length > 0 && <TaxList/>}
            {taxesAndFees.data.length === 0 && <NoTaxes/>}
            {(editModalOpen && selectedTaxOrFee) &&
                <EditTaxOrFeeModal taxOrFee={selectedTaxOrFee} onClose={closeEditModal}/>}
        </>
    );
};
