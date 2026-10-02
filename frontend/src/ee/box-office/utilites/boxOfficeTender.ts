import {t} from "@lingui/macro";
import {BoxOfficeTenderType} from "../../../types.ts";

export const getBoxOfficeTenderLabel = (tender?: BoxOfficeTenderType | null): string | null => {
    switch (tender) {
        case BoxOfficeTenderType.CASH:
            return t`Cash`;
        case BoxOfficeTenderType.CARD:
            return t`Card`;
        case BoxOfficeTenderType.COMP:
            return t`Comp`;
        case BoxOfficeTenderType.OTHER:
            return t`Paid elsewhere`;
        case BoxOfficeTenderType.FREE:
            return t`Free`;
        default:
            return null;
    }
};
