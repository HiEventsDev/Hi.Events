import {t} from "@lingui/macro";
import {useIsReadOnly} from "../../../../../../hooks/useIsCurrentUserAdmin";
import {TaxAndFeeList} from "../../../../../common/TaxAndFeeList";
import {CreateTaxOrFeeModal} from "../../../../../modals/CreateTaxOrFeeModal";
import {useDisclosure} from "@mantine/hooks";
import accountClasses from "../../ManageAccount.module.scss";
import {Card} from "../../../../../common/Card";
import {HeadingCard} from "../../../../../common/HeadingCard";
import {LoadingMask} from "../../../../../common/LoadingMask";

export const TaxSettings = () => {
    const isReadOnly = useIsReadOnly();
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);

    return (
        <>
            <HeadingCard
                heading={t`Tax & Fees`}
                subHeading={t`Manage taxes and fees which can be applied to your products`}
                buttonText={!isReadOnly ? t`Add Tax or Fee` : undefined}
                buttonAction={!isReadOnly ? openCreateModal : undefined}
            />
            <Card className={accountClasses.tabContent}>
                <LoadingMask/>
                <TaxAndFeeList/>
                {createModalOpen && <CreateTaxOrFeeModal onClose={closeCreateModal}/>}
            </Card>
        </>
    );
};

export default TaxSettings;
