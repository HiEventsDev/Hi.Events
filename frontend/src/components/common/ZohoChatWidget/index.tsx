import {useEffect, useRef} from "react";
import {isSsr} from "../../../utilites/helpers.ts";

const ZOHO_ROUTES = ["/manage", "/account", "/welcome"];

const ZohoChatWidget = () => {
    const scriptRef = useRef<HTMLScriptElement | null>(null);

    useEffect(() => {
        if (isSsr()) return;

        try {
            const pathname = window.location.pathname;
            const widgetCode = window.hievents?.VITE_ZOHO_WIDGET_CODE;

            if (!widgetCode || !ZOHO_ROUTES.some(route => pathname.includes(route))) return;

            window.$zoho = window.$zoho || {};
            window.$zoho.salesiq = window.$zoho.salesiq || {
                ready: () => {
                }
            };

            if (!document.getElementById("zsiqscript")) {
                const script = document.createElement("script");
                script.id = "zsiqscript";
                script.src = `https://salesiq.zohopublic.eu/widget?wc=${widgetCode}`;
                script.defer = true;
                document.body.appendChild(script);
                scriptRef.current = script;
            }

            const handleUrlChange = () => {
                try {
                    const currentPath = window.location.pathname;
                    if (!ZOHO_ROUTES.some(route => currentPath.includes(route))) {
                        removeZohoScript();
                    }
                } catch (error) {
                    console.error("Error in ZohoChatWidget URL change handler:", error);
                }
            };

            window.addEventListener("popstate", handleUrlChange);

            return () => {
                window.removeEventListener("popstate", handleUrlChange);
                removeZohoScript();
            };
        } catch (error) {
            console.error("Error initializing ZohoChatWidget:", error);
        }
    }, []);

    const removeZohoScript = () => {
        try {
            const script = document.getElementById("zsiqscript");
            if (script) {
                script.remove();
                scriptRef.current = null;
            }
        } catch (error) {
            console.error("Error removing ZohoChatWidget script:", error);
        }
    };

    return null;
};

export default ZohoChatWidget;
