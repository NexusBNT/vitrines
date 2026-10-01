import { Badge, Button, Checkbox, Group, Loader, Modal, SegmentedControl, SimpleGrid, Stack, Text, TextInput, UnstyledButton } from '@mantine/core';
import { Dropzone } from '@mantine/dropzone';
import { IconCheck, IconPhoto, IconSearch, IconUpload } from '@tabler/icons-react';
import { useMemo, useState } from 'react';

const ACCEPT = ['image/jpeg', 'image/png', 'image/webp'];

/**
 * Photothèque du site : choix d'une ou plusieurs photos, et dépôt de nouvelles photos.
 * `request` : { title, multiple, selected, excludeAi, browse } ; `onResolve(valeur | null)`.
 */
export default function MediaPicker({ request, media, onUpload, onResolve }) {
    const [filter, setFilter] = useState('all');
    const [search, setSearch] = useState('');
    const [picked, setPicked] = useState(() => (request.multiple ? request.selected ?? [] : []));
    const [uploading, setUploading] = useState(0);

    const visible = useMemo(() => media.filter((item) => {
        if (request.excludeAi && item.ai) return false;
        if (filter === 'photos' && item.ai) return false;
        if (filter === 'ai' && !item.ai) return false;

        const haystack = `${item.alt ?? ''} ${item.caption ?? ''} ${item.name ?? ''} ${item.category_label ?? ''}`.toLowerCase();

        return !search || haystack.includes(search.toLowerCase());
    }), [media, filter, search, request.excludeAi]);

    const upload = async (files) => {
        setUploading((count) => count + files.length);

        await Promise.all(files.map(async (file) => {
            try {
                const created = await onUpload(file);

                if (request.multiple && created && !request.excludeAi) {
                    setPicked((current) => [...current, created.id]);
                }
            } finally {
                setUploading((count) => count - 1);
            }
        }));
    };

    const toggle = (item) => {
        if (request.browse || !item.ready) return;

        if (!request.multiple) {
            onResolve(item.id);

            return;
        }

        setPicked((current) => (current.includes(item.id) ? current.filter((id) => id !== item.id) : [...current, item.id]));
    };

    return (
        <Modal opened onClose={() => onResolve(null)} title={request.title ?? 'Photos du site'} size="xl" centered>
            <Stack gap="md">
                <Dropzone onDrop={upload} accept={ACCEPT} maxSize={20 * 1024 * 1024} multiple loading={uploading > 0} className="ed-dropzone">
                    <Group justify="center" gap="sm" mih={64} style={{ pointerEvents: 'none' }}>
                        <Dropzone.Accept><IconUpload size={28} /></Dropzone.Accept>
                        <Dropzone.Idle><IconPhoto size={28} stroke={1.4} /></Dropzone.Idle>
                        <div>
                            <Text fw={600} size="sm">Déposez des photos ici ou cliquez pour les choisir</Text>
                            <Text size="xs" c="dimmed">JPEG, PNG ou WebP. Elles sont optimisées automatiquement (AVIF, WebP, plusieurs tailles).</Text>
                        </div>
                    </Group>
                </Dropzone>

                <Group justify="space-between">
                    {!request.excludeAi ? (
                        <SegmentedControl
                            size="xs"
                            value={filter}
                            onChange={setFilter}
                            data={[{ value: 'all', label: 'Toutes' }, { value: 'photos', label: 'Photos du client' }, { value: 'ai', label: 'Illustrations IA' }]}
                        />
                    ) : (
                        <Text size="xs" c="dimmed">Les illustrations IA ne peuvent pas être présentées comme des réalisations.</Text>
                    )}
                    <TextInput size="xs" leftSection={<IconSearch size={14} />} placeholder="Rechercher" value={search} onChange={(event) => setSearch(event.currentTarget.value)} />
                </Group>

                {visible.length === 0 ? (
                    <Text c="dimmed" ta="center" py="xl">Aucune photo pour l'instant.</Text>
                ) : (
                    <SimpleGrid cols={{ base: 2, sm: 3, md: 4 }} spacing="sm">
                        {visible.map((item) => {
                            const isPicked = request.multiple ? picked.includes(item.id) : request.selected === item.id;

                            return (
                                <UnstyledButton key={item.id} className={`ed-picker-item${isPicked ? ' is-picked' : ''}`} onClick={() => toggle(item)} disabled={!item.ready}>
                                    {item.thumb ? <img src={item.thumb} alt={item.alt ?? ''} loading="lazy" /> : <div className="ed-picker-pending"><Loader size="sm" /></div>}
                                    {item.ai && <Badge className="ed-picker-badge" size="xs" color="violet">IA</Badge>}
                                    {request.multiple && item.ready && <Checkbox className="ed-picker-check" checked={isPicked} readOnly tabIndex={-1} />}
                                    {!request.multiple && isPicked && <span className="ed-picker-current"><IconCheck size={14} /></span>}
                                    <Text size="xs" lineClamp={1} className="ed-picker-caption">{item.alt || item.category_label || item.name}</Text>
                                </UnstyledButton>
                            );
                        })}
                    </SimpleGrid>
                )}

                {request.multiple && (
                    <Group justify="space-between">
                        <Text size="sm" c="dimmed">{picked.length} photo(s) sélectionnée(s)</Text>
                        <Group gap="xs">
                            <Button variant="default" onClick={() => onResolve(null)}>Annuler</Button>
                            <Button onClick={() => onResolve(picked)}>Valider</Button>
                        </Group>
                    </Group>
                )}
            </Stack>
        </Modal>
    );
}
