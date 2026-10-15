import {t} from "@lingui/macro";
import {TaxAndFeeList} from "../../../../../common/TaxAndFeeList";
import {CreateTaxOrFeeModal} from "../../../../../modals/CreateTaxOrFeeModal";
import {useDisclosure} from "@mantine/hooks";
import accountClasses from "../../ManageAccount.module.scss";
import {Card} from "../../../../../common/Card";
import {HeadingCard} from "../../../../../common/HeadingCard";
import {LoadingMask} from "../../../../../common/LoadingMask";
import {useAccess} from "../../../../../../hooks/useAccess.ts";

export const TaxSettings = () => {
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);
    const {isReadOnly} = useAccess();

    return (
        <>
            <HeadingCard
                heading={t`Tax & Fees`}
                subHeading={t`Manage taxes and fees which can be applied to your products`}
                buttonText={t`Add Tax or Fee`}
                buttonAction={isReadOnly ? undefined : openCreateModal}
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
