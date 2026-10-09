import {useParams} from "react-router";
import {useDisclosure} from "@mantine/hooks";
import {Button, Menu} from "@mantine/core";
import {IconCategory, IconChevronDown, IconDownload, IconPlus, IconShoppingCart} from "@tabler/icons-react";
import {PageTitle} from "../../common/PageTitle";
import {PageBody} from "../../common/PageBody";
import {CreateProductModal} from "../../modals/CreateProductModal";
import {ProductCategoryList} from "../../common/ProductsTable";
import {ToolBar} from "../../common/ToolBar";
import {TableSkeleton} from "../../common/TableSkeleton";
import {t} from "@lingui/macro";
import {useUrlHash} from "../../../hooks/useUrlHash.ts";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useGetEventProductCategories} from "../../../queries/useGetProductCategories.ts";
import {SearchBar} from "../../common/SearchBar";
import {useState} from "react";
import {CreateProductCategoryModal} from "../../modals/CreateProductCategoryModal";
import {IdParam, ProductPurchaseStatus} from "../../../types.ts";
import {productClient} from "../../../api/product.client.ts";
import {downloadBinary} from "../../../utilites/download.ts";
import {showError} from "../../../utilites/notifications.tsx";

export const Products = () => {
    const [createProductModalOpen, {
        open: openCreateProductModal,
        close: closeCreateProductModal
    }] = useDisclosure(false);
    const [createProductCategoryModalOpen, {
        open: openCreateProductCategoryModal,
        close: closeCreateProductCategoryModal
    }] = useDisclosure(false);
    const {eventId} = useParams();
    const {data: event} = useGetEvent(eventId);
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedCategoryId, setSelectedCategoryId] = useState<IdParam>(null);
    const [isExporting, setIsExporting] = useState(false);

    const productCategoriesQuery = useGetEventProductCategories(eventId);
    const productCategories = productCategoriesQuery?.data?.data;

    useUrlHash('create-product', () => openCreateProductModal());

    const openCreateProduct = (categoryId: IdParam) => {
        setSelectedCategoryId(() => categoryId);
        openCreateProductModal();
    }

    const exportPurchases = async (statuses?: ProductPurchaseStatus[]) => {
        setIsExporting(true);
        try {
            const blob = await productClient.exportPurchases(eventId, {statuses});
            downloadBinary(blob, 'product-purchases.csv');
        } catch {
            showError(t`Failed to export purchases. Please try again.`);
        } finally {
            setIsExporting(false);
        }
    };

    return (
        <PageBody>
            <PageTitle
                subheading={t`Create and configure tickets and merchandise for sale.`}
            >{t`Tickets & Products`}</PageTitle>

            <ToolBar
                searchComponent={() => (
                    <SearchBar
                        onClear={() => setSearchTerm('')}
                        placeholder={t`Search products`}
                        rightSection={<IconChevronDown/>}
                        value={searchTerm}
                        onChange={(event) => setSearchTerm(event.target.value)}
                    />
                )}
            >
                <Menu position="bottom-end" width={260} withinPortal>
                    <Menu.Target>
                        <Button
                            variant="default"
                            leftSection={<IconDownload size={16}/>}
                            rightSection={<IconChevronDown size={14} stroke={1.5}/>}
                            loading={isExporting}
                            data-testid="product-purchases-export-all-button"
                        >
                            {t`Export purchases`}
                        </Button>
                    </Menu.Target>
                    <Menu.Dropdown>
                        <Menu.Item
                            onClick={() => exportPurchases([ProductPurchaseStatus.Sold, ProductPurchaseStatus.AwaitingPayment])}
                            data-testid="product-purchases-export-active-menu-item"
                        >
                            {t`Active purchases`}
                        </Menu.Item>
                        <Menu.Item onClick={() => exportPurchases()}>
                            {t`All purchases, including cancelled`}
                        </Menu.Item>
                    </Menu.Dropdown>
                </Menu>
                <Menu
                    transitionProps={{transition: 'pop-top-right'}}
                    position="bottom"
                    width={220}
                    withinPortal
                >
                    <Menu.Target>
                        <Button
                            leftSection={<IconPlus/>}
                            color={'green'}
                            data-testid="product-create-button"
                            rightSection={
                                <IconChevronDown stroke={1.5}/>
                            }
                            pr={12}
                        >
                            {t`Create`}
                        </Button>
                    </Menu.Target>
                    <Menu.Dropdown>
                        <Menu.Item
                            leftSection={
                                <IconShoppingCart
                                    stroke={1.5}
                                />
                            }
                            onClick={() => openCreateProduct(undefined)}
                        >
                            {t`Ticket or Product`}
                        </Menu.Item>
                        <Menu.Item
                            leftSection={
                                <IconCategory
                                    stroke={1.5}
                                />
                            }
                            onClick={openCreateProductCategoryModal}
                        >
                            {t`Category`}
                        </Menu.Item>
                    </Menu.Dropdown>
                </Menu>
            </ToolBar>

            <TableSkeleton isVisible={!productCategories || !event}/>

            {(event && productCategories)
                && (<ProductCategoryList
                        initialCategories={productCategories}
                        event={event}
                        searchTerm={searchTerm}
                        onCreateOpen={openCreateProduct}
                    />
                )}

            {createProductModalOpen &&
                <CreateProductModal selectedCategoryId={selectedCategoryId} onClose={closeCreateProductModal}
                                    isOpen={createProductModalOpen}/>}
            {createProductCategoryModalOpen && <CreateProductCategoryModal onClose={closeCreateProductCategoryModal}
                                                                           isOpen={createProductCategoryModalOpen}/>}
        </PageBody>
    );
};

export default Products;
