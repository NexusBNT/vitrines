import { Button, Chip, Group, Loader, Modal, Paper, Stack, Text, Textarea } from '@mantine/core';
import { IconRefresh, IconSparkles } from '@tabler/icons-react';
import { useState } from 'react';

const IDEAS = [
    'Un paragraphe qui présente notre façon de travailler',
    'Les étapes d\'une intervention type',
    'Pourquoi faire appel à un professionnel pour ce service',
    'Les questions à se poser avant de nous contacter',
];

function BlocksPreview({ blocks }) {
    return (
        <div className="ed-ai-preview">
            {blocks.map((block, index) => {
                const text = (block.content ?? []).map((node) => node.text ?? '').join('');

                if (block.type === 'heading') return <h3 key={index}>{text}</h3>;

                if (block.type === 'bulletList') {
                    return (
                        <ul key={index}>
                            {block.content.map((item, itemIndex) => <li key={itemIndex}>{item.content?.[0]?.content?.map((node) => node.text).join('')}</li>)}
                        </ul>
                    );
                }

                return <p key={index}>{text}</p>;
            })}
        </div>
    );
}

/**
 * « / IA » : rédaction d'un passage à partir d'une consigne, avec aperçu avant insertion.
 */
export default function AiWriteModal({ request, api, pageKey, onInsert, onClose }) {
    const [instruction, setInstruction] = useState(request.preset ?? '');
    const [blocks, setBlocks] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const generate = async () => {
        setLoading(true);
        setError(null);

        try {
            const result = await api.aiWrite({ instruction, page: pageKey });
            setBlocks(result.blocks);
        } catch (exception) {
            setError(exception.message);
        } finally {
            setLoading(false);
        }
    };

    return (
        <Modal opened onClose={onClose} size="lg" centered title={<Group gap={8}><IconSparkles size={18} color="var(--mantine-color-violet-6)" /><Text fw={600}>Rédiger avec l'IA</Text></Group>}>
            <Stack gap="md">
                <Textarea
                    autoFocus
                    label="Que voulez-vous ajouter à cette page ?"
                    description="L'IA s'appuie uniquement sur le brief du site et le contenu de la page : aucun prix, label ou délai inventé."
                    placeholder="Ex. : un passage sur l'entretien annuel des chaudières"
                    autosize
                    minRows={3}
                    maxLength={1000}
                    value={instruction}
                    onChange={(event) => setInstruction(event.currentTarget.value)}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter' && (event.metaKey || event.ctrlKey) && instruction.trim()) generate();
                    }}
                />
                {!blocks && (
                    <Chip.Group>
                        <Group gap={6}>
                            {IDEAS.map((idea) => (
                                <Chip key={idea} size="xs" variant="outline" checked={false} onClick={() => setInstruction(idea)}>{idea}</Chip>
                            ))}
                        </Group>
                    </Chip.Group>
                )}

                {loading && <Group gap="sm"><Loader size="xs" type="dots" color="violet" /><Text size="sm" c="dimmed">Rédaction en cours…</Text></Group>}
                {error && <Text size="sm" c="red" style={{ whiteSpace: 'pre-wrap' }}>{error}</Text>}
                {blocks && !loading && (
                    <Paper withBorder p="md" radius="md" bg="var(--mantine-color-body)">
                        <BlocksPreview blocks={blocks} />
                    </Paper>
                )}

                <Group justify="flex-end" gap="xs">
                    <Button variant="default" onClick={onClose}>Annuler</Button>
                    {blocks ? (
                        <>
                            <Button variant="light" color="violet" leftSection={<IconRefresh size={16} />} onClick={generate} loading={loading}>Autre proposition</Button>
                            <Button color="violet" onClick={() => onInsert(blocks)} disabled={loading}>Insérer dans la page</Button>
                        </>
                    ) : (
                        <Button color="violet" leftSection={<IconSparkles size={16} />} onClick={generate} loading={loading} disabled={!instruction.trim()}>Rédiger</Button>
                    )}
                </Group>
            </Stack>
        </Modal>
    );
}
