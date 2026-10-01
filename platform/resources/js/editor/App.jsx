import { Alert, Button, Center, Loader, Stack, Text, ThemeIcon, Title } from '@mantine/core';
import { IconAlertTriangle, IconArrowLeft, IconFileText, IconWand } from '@tabler/icons-react';
import { useEffect, useMemo, useState } from 'react';
import Workspace from './layout/Workspace';
import { createApi } from './lib/api';

export default function App({ apiUrl }) {
    const api = useMemo(() => createApi(apiUrl), [apiUrl]);
    const [boot, setBoot] = useState(null);
    const [error, setError] = useState(null);
    const [creating, setCreating] = useState(false);

    useEffect(() => {
        api.bootstrap().then(setBoot).catch((exception) => setError(exception.message));
    }, [api]);

    useEffect(() => {
        if (!boot?.urls.theme) return undefined;

        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = boot.urls.theme;
        document.head.appendChild(link);

        return () => link.remove();
    }, [boot?.urls.theme]);

    if (error) {
        return (
            <Center h="100vh" p="md">
                <Alert color="red" icon={<IconAlertTriangle />} title="L'éditeur n'a pas pu s'ouvrir" maw={480}>
                    {error}
                </Alert>
            </Center>
        );
    }

    if (!boot) {
        return (
            <Center h="100vh">
                <Loader type="dots" />
            </Center>
        );
    }

    if (!boot.content) {
        const createDraft = async () => {
            setCreating(true);

            try {
                const { content } = await api.createDraft();
                setBoot({ ...boot, content });
            } catch (exception) {
                setError(exception.message);
            }
        };

        return (
            <Center h="100vh" p="md">
                <Stack align="center" gap="md" maw={460} ta="center">
                    <ThemeIcon size={64} radius="xl" variant="light">
                        <IconFileText size={32} />
                    </ThemeIcon>
                    <Title order={2}>{boot.site.name} n'a pas encore de pages</Title>
                    <Text c="dimmed">
                        Partez d'un brouillon construit à partir du brief, puis modifiez-le librement ici. Pour une rédaction complète par l'IA,
                        utilisez « Rédiger avec l'IA » dans les paramètres du site.
                    </Text>
                    <Button size="md" leftSection={<IconWand size={18} />} loading={creating} onClick={createDraft}>
                        Créer un brouillon depuis le brief
                    </Button>
                    <Button variant="subtle" component="a" href={boot.urls.back} leftSection={<IconArrowLeft size={16} />}>
                        Retour aux paramètres du site
                    </Button>
                </Stack>
            </Center>
        );
    }

    return <Workspace api={api} boot={boot} />;
}
