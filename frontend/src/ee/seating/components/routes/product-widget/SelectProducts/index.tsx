import {Skeleton} from "@mantine/core";
import {createLazyModule} from "../../../../../../utilites/lazyModule.ts";
import type {SeatedProductsProps} from "./SeatedProducts.tsx";

const seatedProductsModule = createLazyModule(() => import("./SeatedProducts.tsx"));

export const preloadSeatedProducts = () => seatedProductsModule.load().catch(() => undefined);

export const SeatedProducts = (props: SeatedProductsProps) => {
    const module = seatedProductsModule.useModule();

    if (!module) {
        return <Skeleton height={420} radius={16} mb="md" data-testid="seat-map-loading"/>;
    }

    return <module.SeatedProducts {...props}/>;
};
