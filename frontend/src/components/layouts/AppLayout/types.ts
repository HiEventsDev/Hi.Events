import { ReactNode } from 'react';
import { Icon } from '@tabler/icons-react';

export interface NavItem {
    link?: string;
    label: string;
    icon?: Icon;
    comingSoon?: boolean;
    isActive?: (isActive: boolean) => boolean;
    badge?: string | number | null | undefined;
    badgeColor?: string;
    onClick?: () => void;
    showWhen?: () => boolean | undefined;
    loading?: boolean;
}

export interface BreadcrumbItem {
    link?: string;
    content: ReactNode;
}

export interface StatusToggleConfig {
    status: 'DRAFT' | 'LIVE' | 'PENDING_MANUAL_REVIEW';
    onToggle: () => void;
    statusMessages?: {
        draft?: string;
        live?: string;
    };
}
