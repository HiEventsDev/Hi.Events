/* eslint-disable lingui/no-unlocalized-strings */
import {getConfig} from "../../../utilites/config.ts";
import {CrmSignupFormRequest} from "../api/crm.client.ts";
import {CrmSignupForm} from "../types.ts";

export const signupFormRequest = (form: CrmSignupForm): CrmSignupFormRequest => ({
    name: form.name,
    status: form.status,
    title: form.title,
    description: form.description,
    button_label: form.button_label,
    success_message: form.success_message,
    asks_for_name: form.asks_for_name,
    privacy_url: form.privacy_url,
    tag_ids: form.tag_ids,
    show_on_organizer_page: form.show_on_organizer_page,
});

const frontendOrigin = () => {
    const configured = getConfig('VITE_FRONTEND_URL');
    if (configured) {
        return configured.replace(/\/$/, '');
    }
    return typeof window === 'undefined' ? '' : window.location.origin;
};

export const signupFormUrl = (publicId: string) => `${frontendOrigin()}/subscribe/${publicId}`;

export const signupFormEmbedCode = (publicId: string) => [
    `<div class="hievents-widget" data-hievents-form="${publicId}"></div>`,
    `<script async src="${frontendOrigin()}/widget.js"></script>`,
].join('\n');
