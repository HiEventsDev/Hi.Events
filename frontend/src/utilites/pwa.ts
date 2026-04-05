import {registerSW} from "virtual:pwa-register";

export function initPWA(): void {
    if (typeof window === "undefined") return;

    const updateSW = registerSW({
        onNeedRefresh() {
            if (confirm("A new version of Hi.Events is available. Reload to update?")) {
                updateSW(true);
            }
        },
        onOfflineReady() {
            console.log("[PWA] App ready to work offline");
        },
        onRegisteredSW(swUrl, registration) {
            if (registration) {
                // Check for service worker updates every hour
                setInterval(() => {
                    registration.update();
                }, 60 * 60 * 1000);
            }
        },
    });
}
