import {List, ThemeIcon} from "@mantine/core";
import {IconAlertCircle, IconClock} from "@tabler/icons-react";

interface RequirementsListProps {
    items: string[];
    severity?: "due" | "eventually";
}

const REQUIREMENT_LABELS: Record<string, string> = {
    external_account: "Bank account",
    "individual.verification.document": "Government-issued photo ID",
    "individual.verification.additional_document": "Additional ID verification",
    "individual.address.line1": "Personal address",
    "individual.address.city": "Personal city",
    "individual.address.postal_code": "Personal postal code",
    "individual.dob.day": "Date of birth",
    "individual.email": "Email address",
    "individual.first_name": "First name",
    "individual.last_name": "Last name",
    "individual.phone": "Phone number",
    "individual.ssn_last_4": "Last 4 of SSN",
    "individual.id_number": "Government ID number",
    "business_profile.url": "Business website",
    "business_profile.mcc": "Business category (MCC)",
    "business_profile.product_description": "Business description",
    "business_profile.support_phone": "Customer support phone",
    "company.tax_id": "Business tax ID",
    "company.name": "Legal business name",
    "company.address.line1": "Business address",
    "company.address.city": "Business city",
    "company.address.postal_code": "Business postal code",
    "company.verification.document": "Business verification document",
    "tos_acceptance.date": "Stripe terms acceptance",
    "tos_acceptance.ip": "Stripe terms acceptance",
};

const labelFor = (key: string): string => {
    if (REQUIREMENT_LABELS[key]) return REQUIREMENT_LABELS[key];
    return key
        .replace(/_/g, " ")
        .replace(/\./g, " ‣ ")
        .replace(/\b\w/g, (c) => c.toUpperCase());
};

export const RequirementsList = ({items, severity = "due"}: RequirementsListProps) => {
    if (!items?.length) return null;
    const color = severity === "due" ? "orange" : "blue";
    const Icon = severity === "due" ? IconAlertCircle : IconClock;

    return (
        <List
            spacing={4}
            size="sm"
            icon={
                <ThemeIcon color={color} variant="light" size={20} radius="xl">
                    <Icon size={14}/>
                </ThemeIcon>
            }
        >
            {items.map((key) => (
                <List.Item key={key}>{labelFor(key)}</List.Item>
            ))}
        </List>
    );
};
