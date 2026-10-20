import "dotenv/config";
import * as Sentry from "@sentry/node";

const LOG_SEVERITY_ORDER = ["trace", "debug", "info", "warn", "error", "fatal"];

const LOG_LEVEL_ALIASES = {
    warning: "warn",
    notice: "info",
    critical: "fatal",
    alert: "fatal",
    emergency: "fatal",
};

const isTruthy = (value) => /^(1|true|yes|on)$/i.test(value ?? "");

const resolveMinimumSeverity = (value) => {
    const normalised = LOG_LEVEL_ALIASES[String(value).toLowerCase()] ?? String(value).toLowerCase();
    const index = LOG_SEVERITY_ORDER.indexOf(normalised);

    return index === -1 ? LOG_SEVERITY_ORDER.indexOf("info") : index;
};

const dsn = process.env.SENTRY_SSR_DSN;

if (dsn) {
    const environment = process.env.SENTRY_ENVIRONMENT;

    if (!environment) {
        console.warn(
            `[sentry] SENTRY_ENVIRONMENT is not set, so events will be reported as "${process.env.NODE_ENV || "production"}". ` +
            "Set it per deployment so staging and production stay separate."
        );
    }

    const enableLogs = isTruthy(process.env.SENTRY_ENABLE_LOGS);
    const minimumSeverity = resolveMinimumSeverity(process.env.SENTRY_LOG_LEVEL ?? "info");

    Sentry.init({
        dsn,
        environment: environment || process.env.NODE_ENV || "production",
        release: process.env.SENTRY_RELEASE,
        enableLogs,
        integrations: enableLogs ? [Sentry.consoleLoggingIntegration()] : [],
        beforeSendLog: (log) => (LOG_SEVERITY_ORDER.indexOf(log.level) >= minimumSeverity ? log : null),
        tracesSampleRate: Number(process.env.SENTRY_TRACES_SAMPLE_RATE) || 0,
        dataCollection: {
            userInfo: false,
            cookies: false,
            httpHeaders: { request: false, response: false },
            httpBodies: [],
            urlQueryParams: false,
        },
    });
}
