import {Card} from "../../../common/Card";
import {t} from "@lingui/macro";
import {IconClock} from "@tabler/icons-react";
import {Button, Center, Stack, Text} from "@mantine/core";
import {NavLink} from "react-router";

const PendingApproval = () => {
    return (
        <Center style={{minHeight: '60vh'}}>
            <Card style={{maxWidth: 480, padding: '2rem', textAlign: 'center'}}>
                <Stack gap="md" align="center">
                    <IconClock size={48} color="orange"/>
                    <h2 style={{margin: 0}}>{t`Account Pending Approval`}</h2>
                    <Text size="md" c="dimmed">
                        {t`Your account has been created and is awaiting admin approval. You will receive an email notification once your account has been approved and you can log in.`}
                    </Text>
                    <Button component={NavLink} to="/auth/login" variant="subtle" mt="md">
                        {t`Back to Login`}
                    </Button>
                </Stack>
            </Card>
        </Center>
    );
};

export default PendingApproval;
