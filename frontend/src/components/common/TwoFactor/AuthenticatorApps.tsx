import {t} from "@lingui/macro";
import {IconExternalLink} from "@tabler/icons-react";
import classes from "./TwoFactor.module.scss";

const apps = [
    {
        name: 'Ente Auth',
        url: 'https://ente.io/auth',
        description: () => t`Free, open source, with end-to-end encrypted backups`,
        recommended: true,
    },
    {
        name: 'Google Authenticator',
        url: 'https://support.google.com/accounts/answer/1066447',
        description: () => t`Simple and widely used, with optional cloud sync`,
        recommended: false,
    },
];

export const AuthenticatorApps = () => {
    return (
        <div className={classes.apps}>
            {apps.map(app => (
                <a key={app.name} href={app.url} target="_blank" rel="noopener noreferrer" className={classes.app}>
                    <span className={classes.appText}>
                        <span className={classes.appName}>
                            {app.name}
                            {app.recommended && <span className={classes.recommended}>{t`Recommended`}</span>}
                        </span>
                        <span className={classes.appDescription}>{app.description()}</span>
                    </span>
                    <IconExternalLink size={16} className={classes.appLink}/>
                </a>
            ))}
        </div>
    );
};
