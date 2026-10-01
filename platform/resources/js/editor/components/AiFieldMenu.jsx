import { ActionIcon, Button, Group, Menu, Popover, ScrollArea, Stack, Text, Textarea, Tooltip } from '@mantine/core';
import { IconCheck, IconRefresh, IconSparkles, IconX } from '@tabler/icons-react';
import { useState } from 'react';
import { AI_ACTIONS } from '../lib/aiActions';
import { useWorkspaceContext } from '../lib/context';

/**
 * Bouton IA d'un champ de section : réécriture du texte, avec aperçu avant de l'appliquer.
 */
export default function AiFieldMenu({ value, onApply, label = 'ce texte' }) {
    const { api, aiAvailable, currentKey, notifyError } = useWorkspaceContext();
    const [result, setResult] = useState(null);
    const [loading, setLoading] = useState(false);
    const [lastRequest, setLastRequest] = useState(null);
    const [custom, setCustom] = useState(false);
    const [instruction, setInstruction] = useState('');

    if (!aiAvailable) return null;

    const run = async (action, extra = null) => {
        setCustom(false);
        setLoading(true);
        setLastRequest({ action, extra });

        try {
            const response = await api.aiTransform({ action, text: value, instruction: extra, page: currentKey });
            setResult(response.text);
        } catch (exception) {
            notifyError('L\'IA n\'a pas pu proposer de texte', exception.message);
        } finally {
            setLoading(false);
        }
    };

    const close = () => {
        setResult(null);
        setCustom(false);
    };

    return (
        <Popover opened={result !== null || custom} onChange={(opened) => !opened && close()} width={380} position="bottom-end" shadow="lg" withinPortal trapFocus={custom}>
            <Popover.Target>
                <span className="ed-inline-ai">
                    <Menu position="bottom-end" shadow="md" width={220} withinPortal disabled={loading || !value.trim()}>
                        <Menu.Target>
                            <Tooltip label={value.trim() ? `Retravailler ${label} avec l'IA` : 'Écrivez d\'abord un texte'} withinPortal>
                                <ActionIcon size="sm" variant="light" color="violet" loading={loading} aria-label="IA">
                                    <IconSparkles size={14} />
                                </ActionIcon>
                            </Tooltip>
                        </Menu.Target>
                        <Menu.Dropdown>
                            {AI_ACTIONS.map((action) => (
                                <Menu.Item key={action.id} leftSection={<action.icon size={16} />} onClick={() => run(action.id)}>
                                    {action.label}
                                </Menu.Item>
                            ))}
                            <Menu.Divider />
                            <Menu.Item leftSection={<IconSparkles size={16} />} onClick={() => setCustom(true)}>Consigne libre…</Menu.Item>
                        </Menu.Dropdown>
                    </Menu>
                </span>
            </Popover.Target>
            <Popover.Dropdown>
                {custom ? (
                    <Stack gap="xs">
                        <Textarea
                            autoFocus
                            label="Consigne pour l'IA"
                            placeholder="Ex. : mettre en avant le travail soigné, en deux phrases"
                            value={instruction}
                            onChange={(event) => setInstruction(event.currentTarget.value)}
                            autosize
                            minRows={2}
                            maxLength={500}
                        />
                        <Group justify="flex-end" gap="xs">
                            <Button variant="default" size="xs" onClick={close}>Annuler</Button>
                            <Button size="xs" color="violet" disabled={!instruction.trim()} onClick={() => run('custom', instruction)}>Proposer</Button>
                        </Group>
                    </Stack>
                ) : (
                    <Stack gap="xs">
                        <Text size="xs" fw={600} c="violet">Proposition de l'IA</Text>
                        <ScrollArea.Autosize mah={260}>
                            <Text size="sm" style={{ whiteSpace: 'pre-wrap' }}>{result}</Text>
                        </ScrollArea.Autosize>
                        <Group justify="space-between" gap="xs">
                            <Button variant="subtle" size="xs" color="gray" leftSection={<IconRefresh size={14} />} loading={loading} onClick={() => run(lastRequest.action, lastRequest.extra)}>
                                Autre proposition
                            </Button>
                            <Group gap="xs">
                                <ActionIcon variant="default" onClick={close} aria-label="Ignorer"><IconX size={16} /></ActionIcon>
                                <Button size="xs" color="violet" leftSection={<IconCheck size={14} />} onClick={() => { onApply(result); close(); }}>Remplacer</Button>
                            </Group>
                        </Group>
                    </Stack>
                )}
            </Popover.Dropdown>
        </Popover>
    );
}
