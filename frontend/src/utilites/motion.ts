const REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)';

export const scrollBehavior = (): ScrollBehavior =>
    typeof window !== 'undefined' && window.matchMedia(REDUCED_MOTION_QUERY).matches ? 'auto' : 'smooth';
