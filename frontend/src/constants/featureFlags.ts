export const FeatureFlag = {
    SEATING: 'seating',
    BOX_OFFICE: 'box_office',
} as const;

export type FeatureFlagKey = typeof FeatureFlag[keyof typeof FeatureFlag];
