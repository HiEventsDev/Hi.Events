import {t} from "@lingui/macro";

export const statusColor = (status: string) => {
    switch (status?.toUpperCase()) {
        case 'DELIVERED':
            return 'green';
        case 'SENT':
            return 'teal';
        case 'BOUNCED':
            return 'red';
        case 'FAILED':
            return 'orange';
        case 'SUPPRESSED':
            return 'gray';
        default:
            return 'blue';
    }
};

export const emailTypeLabel = (emailType: string) => {
    switch (emailType) {
        case 'order_summary':
            return t`Order Confirmation`;
        case 'order_failed':
            return t`Order Failed`;
        case 'attendee_ticket':
            return t`Attendee Ticket`;
        case 'waitlist_offer':
            return t`Waitlist Offer`;
        case 'waitlist_confirmation':
            return t`Waitlist Confirmation`;
        case 'waitlist_offer_expired':
            return t`Waitlist Offer Expired`;
        default:
            return emailType;
    }
};

export const statusFilterOptions = [
    {label: 'SENT', value: 'SENT'},
    {label: 'DELIVERED', value: 'DELIVERED'},
    {label: 'BOUNCED', value: 'BOUNCED'},
    {label: 'FAILED', value: 'FAILED'},
    {label: 'SUPPRESSED', value: 'SUPPRESSED'},
];

export const dateRangeOptions = [
    {label: t`Day`, value: '1d'},
    {label: t`Week`, value: '7d'},
    {label: t`Month`, value: '30d'},
    {label: t`Qtr`, value: '90d'},
    {label: t`Year`, value: '365d'},
    {label: t`All`, value: 'all'},
];

export const emailTypeFilterOptions = [
    {label: t`Order Confirmation`, value: 'order_summary'},
    {label: t`Order Failed`, value: 'order_failed'},
    {label: t`Attendee Ticket`, value: 'attendee_ticket'},
    {label: t`Waitlist Offer`, value: 'waitlist_offer'},
    {label: t`Waitlist Confirmation`, value: 'waitlist_confirmation'},
    {label: t`Waitlist Offer Expired`, value: 'waitlist_offer_expired'},
];
