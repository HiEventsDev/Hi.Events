import { useEffect, useRef } from "react";
import { isSsr } from "../../../utilites/helpers.ts";
import { useGetMe } from "../../../queries/useGetMe.ts";
import { User } from "../../../types.ts";
import { getConfig } from "../../../utilites/config.ts";

const CHATWOOT_ROUTES = ["/manage", "/account", "/welcome"];

const ChatwootWidget = () => {
    const scriptRef = useRef<HTMLScriptElement | null>(null);
    const { data: me, isLoading } = useGetMe();
    const chatwootToken = getConfig('VITE_CHATWOOT_WEBSITE_TOKEN');
    const chatwootUrl = getConfig('VITE_CHATWOOT_BASE_URL') || 'https://app.chatwoot.com';

    useEffect(() => {
        if (isSsr() || isLoading || !chatwootToken) {
            return;
        }

        try {
            const pathname = window.location.pathname;
            if (!CHATWOOT_ROUTES.some(route => pathname.includes(route))) return;

            if (!document.getElementById("chatwoot-script")) {
                const script = document.createElement("script");
                script.id = "chatwoot-script";
                script.src = `${chatwootUrl}/packs/js/sdk.js`;
                script.defer = true;
                script.async = true;
                script.onload = () => {
                    window.chatwootSDK.run({
                        websiteToken: chatwootToken,
                        baseUrl: chatwootUrl
                    });
                    if (me) {
                        setChatwootUserDetails(me);
                    }
                };
                document.body.appendChild(script);
                scriptRef.current = script;
            } else if (me) {
                setChatwootUserDetails(me);
            }

            const handleUrlChange = () => {
                try {
                    const currentPath = window.location.pathname;
                    if (!CHATWOOT_ROUTES.some(route => currentPath.includes(route))) {
                        removeChatwootScript();
                    }
                } catch (error) {
                    console.error("Error in ChatwootWidget URL change handler:", error);
                }
            };

            window.addEventListener("popstate", handleUrlChange);

            return () => {
                window.removeEventListener("popstate", handleUrlChange);
                removeChatwootScript();
            };
        } catch (error) {
            console.error("Error initializing ChatwootWidget:", error);
        }
    }, [isLoading, me]);

    const removeChatwootScript = () => {
        try {
            const script = document.getElementById("chatwoot-script");
            if (script) {
                script.remove();
                scriptRef.current = null;
            }
        } catch (error) {
            console.error("Error removing ChatwootWidget script:", error);
        }
    };

    const setChatwootUserDetails = (user: User) => {
        try {
            window.addEventListener("chatwoot:ready", () => {
                if (!window.$chatwoot) return;

                window.$chatwoot.setUser(String(user.id), {
                    email: user.email,
                    name: user.full_name,
                    locale: user.locale || "en",
                });

                window.$chatwoot.setCustomAttributes({
                    locale: user.locale || "en",
                    account_id: user.account_id || "Unknown",
                    user_role: user.role || "Unknown",
                    is_user_verified: user.is_email_verified,
                });
            });
        } catch (error) {
            console.error("Error setting Chatwoot user details:", error);
        }
    };

    return null;
};

export default ChatwootWidget;
