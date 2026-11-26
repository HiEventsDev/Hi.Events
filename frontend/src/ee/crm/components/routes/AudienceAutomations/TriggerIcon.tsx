import {IconAlarm, IconCalendarPlus, IconHeartHandshake, IconRefresh, IconShoppingCartX, IconSparkles, IconUsersPlus} from "@tabler/icons-react";
import {CrmAutomationTrigger} from "../../../types.ts";

const ICONS: Record<CrmAutomationTrigger, typeof IconSparkles> = {
    EVENT_PUBLISHED: IconCalendarPlus,
    AFTER_EVENT: IconHeartHandshake,
    BEFORE_EVENT: IconAlarm,
    ABANDONED_CHECKOUT: IconShoppingCartX,
    SEGMENT_ENTERED: IconUsersPlus,
    WIN_BACK: IconRefresh,
    WELCOME: IconSparkles,
};

export const TriggerIcon = ({trigger}: {trigger: CrmAutomationTrigger}) => {
    const Icon = ICONS[trigger];

    return <Icon size={18} stroke={1.7}/>;
};
