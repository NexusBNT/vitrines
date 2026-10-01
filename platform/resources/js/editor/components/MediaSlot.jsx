import { ActionIcon, Badge, Button, Group, Tooltip } from '@mantine/core';
import { IconPhotoPlus, IconReplace, IconTrash } from '@tabler/icons-react';
import { useWorkspaceContext } from '../lib/context';

/**
 * Emplacement d'image d'une section : affiche la photo choisie, ou un bouton pour en choisir une.
 */
export default function MediaSlot({ mediaId, onChange, className = '', label = 'Choisir une photo', excludeAi = false, compact = false }) {
    const { mediaById, pickMedia } = useWorkspaceContext();
    const media = mediaId ? mediaById[mediaId] : null;

    const choose = async () => {
        const selected = await pickMedia({ selected: mediaId, excludeAi, title: label });

        if (selected) onChange(selected);
    };

    if (!media) {
        return (
            <div className={`ed-media-empty ${className}`} contentEditable={false}>
                <Button variant="light" size={compact ? 'xs' : 'sm'} leftSection={<IconPhotoPlus size={16} />} onClick={choose}>
                    {label}
                </Button>
            </div>
        );
    }

    return (
        <div className={`ed-media ${className}`} contentEditable={false}>
            {media.large ? <img src={media.large} alt={media.alt ?? ''} draggable={false} /> : <div className="ed-media-pending">Traitement de la photo…</div>}
            {media.ai && <Badge className="ed-media-badge" size="xs" color="violet" variant="filled">Illustration IA</Badge>}
            <Group gap={4} className="ed-media-actions">
                <Tooltip label="Changer de photo" withinPortal>
                    <ActionIcon variant="white" color="dark" onClick={choose} aria-label="Changer de photo"><IconReplace size={16} /></ActionIcon>
                </Tooltip>
                <Tooltip label="Retirer la photo" withinPortal>
                    <ActionIcon variant="white" color="red" onClick={() => onChange(null)} aria-label="Retirer la photo"><IconTrash size={16} /></ActionIcon>
                </Tooltip>
            </Group>
        </div>
    );
}
