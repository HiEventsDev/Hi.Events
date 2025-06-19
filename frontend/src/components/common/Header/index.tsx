import {Container} from '@mantine/core';
import classes from './Header.module.scss';
import {NavLink} from "react-router";
import { getConfig } from '../../../utilites/config';

interface HeaderProps {
    rightContent?: React.ReactNode;
    fullWidth?: boolean;
}

export const Header = ({rightContent, fullWidth = false}: HeaderProps) => {
    return (
        <header className={classes.header}>
            <Container size="md" className={classes.inner} fluid={fullWidth}>
                <NavLink className={classes.logo} to={'/manage/events'}>
                    <img src={getConfig("VITE_APP_LOGO_LIGHT", "/logo-text-only-white-text.png")} alt={`${getConfig("VITE_APP_NAME", "Hi.Events")} logo`} className={classes.logo}/>
                </NavLink>

                <div className={classes.rightContent}>
                    {rightContent}
                </div>
            </Container>
        </header>
    );
}
