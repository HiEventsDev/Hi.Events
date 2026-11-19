export const FeatureFlag = {
    SEATING: 'seating',
    BOX_OFFICE: 'box_office',
    ENTERPRISE_SSO: 'enterprise_sso',
    CRM: 'crm',
    CRM_SMS: 'crm_sms',
} as const;

export type FeatureFlagKey = typeof FeatureFlag[keyof typeof FeatureFlag];

export const licensedFeatureOf = (flag: FeatureFlagKey): Exclude<FeatureFlagKey, typeof FeatureFlag.CRM_SMS> =>
    flag === FeatureFlag.CRM_SMS ? FeatureFlag.CRM : flag;

