export const FeatureFlag = {
    SEATING: 'seating',
    BOX_OFFICE: 'box_office',
    ENTERPRISE_SSO: 'enterprise_sso',
} as const;

export type FeatureFlagKey = typeof FeatureFlag[keyof typeof FeatureFlag];

