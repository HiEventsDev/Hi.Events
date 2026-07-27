import {Badge, Group, Stack, Text} from "@mantine/core";
import {t} from "@lingui/macro";

interface CapabilityListProps {
    capabilities: Record<string, string>;
}

const CAPABILITY_LABELS: Record<string, string> = {
    card_payments: "Card payments",
    transfers: "Transfers",
    link_payments: "Link",
    klarna_payments: "Klarna",
    afterpay_clearpay_payments: "Afterpay / Clearpay",
    affirm_payments: "Affirm",
    bancontact_payments: "Bancontact",
    eps_payments: "EPS",
    ideal_payments: "iDEAL",
    sofort_payments: "SOFORT",
    sepa_debit_payments: "SEPA Direct Debit",
    p24_payments: "Przelewy24",
    blik_payments: "BLIK",
    mb_way_payments: "MB Way",
    revolut_pay_payments: "Revolut Pay",
    pix_payments: "Pix",
    boleto_payments: "Boleto",
    apple_pay: "Apple Pay",
    google_pay: "Google Pay",
    cashapp_payments: "Cash App Pay",
    amazon_pay_payments: "Amazon Pay",
    samsung_pay_payments: "Samsung Pay",
    payco_payments: "Payco",
    kakao_pay_payments: "Kakao Pay",
    naver_pay_payments: "Naver Pay",
    cartes_bancaires_payments: "Cartes Bancaires",
    grabpay_payments: "GrabPay",
    fpx_payments: "FPX",
    promptpay_payments: "PromptPay",
    konbini_payments: "Konbini",
    oxxo_payments: "OXXO",
    us_bank_account_ach_payments: "ACH Direct Debit",
    bacs_debit_payments: "Bacs Direct Debit",
    au_becs_debit_payments: "BECS Direct Debit",
    acss_debit_payments: "Pre-authorized debit (Canada)",
    multibanco_payments: "Multibanco",
};

const humanize = (key: string): string =>
    key
        .replace(/_payments?$/i, "")
        .replace(/_/g, " ")
        .replace(/\b\w/g, (c) => c.toUpperCase());

const labelFor = (key: string): string => CAPABILITY_LABELS[key] ?? humanize(key);

const statusBadge = (status: string) => {
    if (status === "active") {
        return <Badge color="green" variant="light">{t`Active`}</Badge>;
    }
    if (status === "pending") {
        return <Badge color="yellow" variant="light">{t`Pending`}</Badge>;
    }
    return <Badge color="gray" variant="light">{t`Inactive`}</Badge>;
};

export const CapabilityList = ({capabilities}: CapabilityListProps) => {
    const entries = Object.entries(capabilities ?? {}).sort(([a], [b]) => labelFor(a).localeCompare(labelFor(b)));

    if (entries.length === 0) {
        return <Text c="dimmed" size="sm">{t`No capabilities reported by Stripe yet.`}</Text>;
    }

    return (
        <Stack gap={6}>
            {entries.map(([key, status]) => (
                <Group key={key} justify="space-between" wrap="nowrap">
                    <Text size="sm">{labelFor(key)}</Text>
                    {statusBadge(status)}
                </Group>
            ))}
        </Stack>
    );
};
