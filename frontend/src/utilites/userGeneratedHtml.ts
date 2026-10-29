export const USER_GENERATED_LINK_REL = 'nofollow ugc noopener noreferrer';

const ANCHOR_TAG = /<a\b([^>]*)>/gi;

const REL_OR_TARGET_ATTRIBUTE = /\s(?:rel|target)\s*=\s*(?:"[^"]*"|'[^']*'|[^\s>]+)/gi;

export const applyUserGeneratedLinkSafety = (html: string): string =>
    html.replace(ANCHOR_TAG, (_tag, attributes: string) => {
        const stripped = attributes.replace(REL_OR_TARGET_ATTRIBUTE, '').trimEnd();

        return `<a${stripped} rel="${USER_GENERATED_LINK_REL}" target="_blank">`;
    });
