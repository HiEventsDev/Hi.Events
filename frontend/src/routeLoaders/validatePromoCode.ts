import {promoCodeClientPublic} from "../api/promo-code.client.ts";

export const validatePromoCode = async (eventId: string | undefined, promoCode: string): Promise<boolean | undefined> => {
    try {
        const {valid} = await promoCodeClientPublic.validateCode(eventId, promoCode);
        return valid;
    } catch {
        return undefined;
    }
};
