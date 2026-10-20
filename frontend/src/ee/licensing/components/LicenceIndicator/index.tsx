import {Indicator} from "@mantine/core";
import {ReactNode} from "react";
import {LICENCE_TONE_COLORS, LicenceNotice} from "../../licenceNotice.ts";

interface LicenceIndicatorProps {
    notice: LicenceNotice | null;
    children: ReactNode;
}

export const LicenceIndicator = ({notice, children}: LicenceIndicatorProps) => {
    if (!notice) {
        return <>{children}</>;
    }

    return (
        <Indicator
            inline
            position="bottom-start"
            size={16}
            offset={5}
            withBorder
            color={LICENCE_TONE_COLORS[notice.tone]}
            label={
                <span data-testid="licence-badge" data-tone={notice.tone}>
                    {notice.tone === 'info' ? 'i' : '!'}
                </span>
            }
        >
            {children}
        </Indicator>
    );
};
