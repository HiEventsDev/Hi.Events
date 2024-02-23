import { Title, Text, Button, Container, Group } from '@mantine/core';

export default function ErrorPage() {
    return (
        <div >
            <Container>
                <div >500</div>
                <Title>Something bad just happened...</Title>
                <Text size="lg" align="center">
                    Our servers could not handle your request. Don&apos;t worry, our development team was
                    already notified. Try refreshing the page.
                </Text>
                <Group position="center">
                    <Button variant="white" size="md">
                        Refresh the page
                    </Button>
                </Group>
            </Container>
        </div>
    );
}