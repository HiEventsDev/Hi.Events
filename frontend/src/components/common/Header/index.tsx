import {Container} from '@mantine/core';
import classes from './Header.module.scss';
import {GlobalMenu} from "../GlobalMenu";
import {NavLink} from "react-router-dom";

export const Header = () => {
    return (
        <header className={classes.header}>
            <Container size="md" className={classes.inner}>
                <NavLink className={classes.logo} to={'/manage/events'}>
                    <img src="/logo-text-only-white-text.png" alt="Hi.Events logo" className={classes.logo}/>
                </NavLink>
                <GlobalMenu/>
            </Container>
        </header>
    );
}