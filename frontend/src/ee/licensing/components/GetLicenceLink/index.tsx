import {Anchor, AnchorProps} from "@mantine/core";
import {t} from "@lingui/macro";
import {licensingUrl} from "../../licensingUrl.ts";

interface GetLicenceLinkProps extends AnchorProps {
    source: string;
}

export const GetLicenceLink = ({source, ...anchorProps}: GetLicenceLinkProps) => (
    <Anchor href={licensingUrl(source)} target="_blank" rel="noopener" {...anchorProps}>
        {t`Get a licence`}
    </Anchor>
);
