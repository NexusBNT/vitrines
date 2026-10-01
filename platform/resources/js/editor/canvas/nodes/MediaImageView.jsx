import { ActionIcon, Group, SegmentedControl, Tooltip } from '@mantine/core';
import { IconReplace, IconTrash } from '@tabler/icons-react';
import { NodeViewWrapper } from '@tiptap/react';
import InlineText from '../../components/InlineText';
import MediaSlot from '../../components/MediaSlot';
import { useWorkspaceContext } from '../../lib/context';

/**
 * Image insérée dans le texte : largeur (texte ou pleine largeur) et légende.
 */
export default function MediaImageView({ node, updateAttributes, deleteNode, selected }) {
    const { mediaById, pickMedia } = useWorkspaceContext();
    const { media: mediaId, caption, size } = node.attrs;
    const media = mediaById[mediaId];

    const replace = async () => {
        const selectedId = await pickMedia({ selected: mediaId, title: 'Remplacer l\'image' });

        if (selectedId) updateAttributes({ media: selectedId });
    };

    return (
        <NodeViewWrapper className={`ed-block-media${selected ? ' is-selected' : ''}`}>
            <figure className={`rt-figure${size === 'wide' ? ' rt-figure--wide' : ''}`} contentEditable={false}>
                {media ? (
                    <div className="ed-figure-image">
                        {media.large ? <img src={media.large} alt={media.alt ?? ''} draggable={false} /> : <div className="ed-media-pending">Traitement de la photo…</div>}
                        <Group gap={6} className="ed-figure-tools">
                            <SegmentedControl size="xs" value={size} onChange={(value) => updateAttributes({ size: value })} data={[{ value: 'normal', label: 'Largeur du texte' }, { value: 'wide', label: 'Large' }]} />
                            <Tooltip label="Remplacer" withinPortal><ActionIcon variant="white" color="dark" onClick={replace} aria-label="Remplacer"><IconReplace size={16} /></ActionIcon></Tooltip>
                            <Tooltip label="Supprimer" withinPortal><ActionIcon variant="white" color="red" onClick={deleteNode} aria-label="Supprimer"><IconTrash size={16} /></ActionIcon></Tooltip>
                        </Group>
                    </div>
                ) : (
                    <MediaSlot mediaId={null} onChange={(id) => updateAttributes({ media: id })} label="Choisir une image" />
                )}
                <figcaption>
                    <InlineText value={caption} onChange={(value) => updateAttributes({ caption: value || null })} placeholder="Légende (facultative)" maxLength={300} />
                </figcaption>
            </figure>
        </NodeViewWrapper>
    );
}
