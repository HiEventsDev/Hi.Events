import {t} from "@lingui/macro";
import {Stack, Text, Button, ThemeIcon} from "@mantine/core";
import {IconLinkOff} from "@tabler/icons-react";
import {useNavigate} from "react-router";
import {Card} from "../../../../common/Card";

export const LinkExpiredMessage = () => {
    const navigate = useNavigate();

    const handleLookupTickets = () => {
        navigate('/auth/login');
    };

    return (
        <Card style={{textAlign: 'center', padding: '48px 24px'}}>
            <Stack align="center" gap="lg">
                <ThemeIcon
                    size={64}
                    radius="xl"
                    variant="light"
                    color="red"
                >
                    <IconLinkOff size={32}/>
                </ThemeIcon>
                <div>
                    <Text size="xl" fw={600} mb="xs">
                        {t`This link is no longer valid`}
                    </Text>
                    <Text size="sm" c="dimmed">
                        {t`The link you are trying to access has expired or is no longer valid. Please check your email for an updated link to manage your order.`}
                    </Text>
                </div>
                <Button
                    onClick={handleLookupTickets}
                    size="md"
                >
                    {t`Look Up My Tickets`}
                </Button>
            </Stack>
        </Card>
    );
};
