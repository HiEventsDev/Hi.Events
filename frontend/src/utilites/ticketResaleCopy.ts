import {t} from "@lingui/macro";

export const ticketResaleDescription = (): string =>
    t`Buyers who can't attend can list a ticket. Once that ticket type is sold out, the listed ticket is sold through your normal checkout and the seller is refunded to their original card, minus the card and platform fees of the replacement sale, so you are not out of pocket. You can turn this off for individual ticket types. If a replacement buyer later disputes their payment and wins, that chargeback is at your risk, as with any sale.`;

export const resaleToggleRefusedMessage = (): string =>
    t`Only members who can issue refunds can turn ticket resale on or off.`;
