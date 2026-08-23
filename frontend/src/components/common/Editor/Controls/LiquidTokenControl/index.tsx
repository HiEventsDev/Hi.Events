import {prefetchOnIdle} from "../../../../../utilites/helpers.ts";
import {createLazyModule} from "../../../../../utilites/lazyModule.ts";
import type {LiquidTokenControlProps} from "./LiquidTokenControl";

const liquidTokenControlModule = createLazyModule(() => import("./LiquidTokenControl"));

prefetchOnIdle(liquidTokenControlModule.load);

export const LiquidTokenControl = (props: LiquidTokenControlProps) => {
    const module = liquidTokenControlModule.useModule();

    if (!module) {
        return null;
    }

    return <module.LiquidTokenControl {...props}/>;
};
