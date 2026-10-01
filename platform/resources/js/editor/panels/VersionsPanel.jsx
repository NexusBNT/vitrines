import { Badge, Button, Group, Loader, ScrollArea, Stack, Text, UnstyledButton } from '@mantine/core';
import { modals } from '@mantine/modals';
import { IconArrowBackUp, IconHistory } from '@tabler/icons-react';
import { diffWords } from 'diff';
import { useEffect, useState } from 'react';
import { useWorkspaceContext } from '../lib/context';
import PanelHeader from './PanelHeader';

const dateFormat = new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium', timeStyle: 'short' });

const SOURCE_COLORS = { ai: 'violet', editor: 'indigo', restore: 'orange', design: 'pink', draft: 'gray', initial: 'gray', system: 'gray' };

function PageDiff({ before, after }) {
    const parts = diffWords(before ?? '', after ?? '');

    return (
        <Text size="sm" className="ed-diff">
            {parts.map((part, index) => (
                <span key={index} className={part.added ? 'is-added' : part.removed ? 'is-removed' : undefined}>{part.value}</span>
            ))}
        </Text>
    );
}

/**
 * Historique : liste des versions, comparaison avec la version actuelle, restauration.
 */
export default function VersionsPanel({ onClose, onRestored, onFlush }) {
    const { api, notifyError } = useWorkspaceContext();
    const [revisions, setRevisions] = useState(null);
    const [selected, setSelected] = useState(null);
    const [detail, setDetail] = useState(null);

    useEffect(() => {
        onFlush().finally(() => api.revisions().then((data) => setRevisions(data.revisions)).catch((exception) => notifyError('Historique indisponible', exception.message)));
    }, [api, onFlush, notifyError]);

    useEffect(() => {
        if (!selected) return;

        setDetail(null);
        api.revision(selected).then(setDetail).catch((exception) => notifyError('Version indisponible', exception.message));
    }, [api, selected, notifyError]);

    const restore = () => modals.openConfirmModal({
        title: 'Restaurer cette version ?',
        children: <Text size="sm">Les pages reprendront le contenu de cette version. La version actuelle reste dans l'historique : vous pourrez y revenir.</Text>,
        labels: { confirm: 'Restaurer', cancel: 'Annuler' },
        confirmProps: { color: 'orange' },
        onConfirm: async () => {
            try {
                await onFlush();
                const { content } = await api.restore(selected);
                onRestored(content);
            } catch (exception) {
                notifyError('Restauration impossible', exception.message);
            }
        },
    });

    const changedPages = detail
        ? [...new Set([...detail.pages.map((page) => page.key), ...detail.current.map((page) => page.key)])].map((key) => ({
            key,
            before: detail.pages.find((page) => page.key === key),
            after: detail.current.find((page) => page.key === key),
        })).filter(({ before, after }) => !before || !after || before.text !== after.text || before.title !== after.title || before.nav_label !== after.nav_label)
        : [];

    return (
        <aside className="ed-panel ed-panel--wide">
            <PanelHeader title="Historique des versions" icon={IconHistory} onClose={onClose} />
            <div className="ed-versions">
                <ScrollArea className="ed-versions-list" type="auto">
                    {revisions === null ? (
                        <Group justify="center" p="lg"><Loader size="sm" /></Group>
                    ) : revisions.length === 0 ? (
                        <Text size="sm" c="dimmed" p="md">Aucune version enregistrée pour l'instant.</Text>
                    ) : (
                        revisions.map((revision, index) => (
                            <UnstyledButton key={revision.id} className={`ed-version${selected === revision.id ? ' is-active' : ''}`} onClick={() => setSelected(revision.id)}>
                                <Text size="sm" fw={600}>{dateFormat.format(new Date(revision.updated_at))}</Text>
                                <Group gap={6} mt={2}>
                                    <Badge size="xs" variant="light" color={SOURCE_COLORS[revision.source] ?? 'gray'}>{revision.label}</Badge>
                                    {index === 0 && <Badge size="xs" variant="outline" color="teal">Actuelle</Badge>}
                                </Group>
                                {revision.user && <Text size="xs" c="dimmed" mt={2}>{revision.user}</Text>}
                            </UnstyledButton>
                        ))
                    )}
                </ScrollArea>
                <ScrollArea className="ed-versions-detail" type="auto">
                    {!selected && <Text size="sm" c="dimmed" p="md">Choisissez une version pour la comparer au contenu actuel.</Text>}
                    {selected && !detail && <Group justify="center" p="lg"><Loader size="sm" /></Group>}
                    {detail && (
                        <Stack gap="md" p="md">
                            <Group justify="space-between">
                                <Text size="sm" c="dimmed">Différences avec la version actuelle : <span className="ed-diff-legend is-removed">retiré</span> <span className="ed-diff-legend is-added">ajouté</span></Text>
                                <Button size="xs" color="orange" leftSection={<IconArrowBackUp size={14} />} onClick={restore} disabled={selected === revisions?.[0]?.id}>Restaurer</Button>
                            </Group>
                            {changedPages.length === 0 && <Text size="sm" c="dimmed">Identique au contenu actuel.</Text>}
                            {changedPages.map(({ key, before, after }) => (
                                <div key={key} className="ed-diff-page">
                                    <Group gap={8} mb={6}>
                                        <Text fw={600} size="sm">{(after ?? before).nav_label}</Text>
                                        {!before && <Badge size="xs" color="teal">Page ajoutée depuis</Badge>}
                                        {!after && <Badge size="xs" color="red">Page supprimée depuis</Badge>}
                                    </Group>
                                    {before && after && before.title !== after.title && (
                                        <Text size="xs" c="dimmed" mb={4}>Titre Google : <PageDiff before={before.title} after={after.title} /></Text>
                                    )}
                                    <PageDiff before={before?.text ?? ''} after={after?.text ?? ''} />
                                </div>
                            ))}
                        </Stack>
                    )}
                </ScrollArea>
            </div>
        </aside>
    );
}
