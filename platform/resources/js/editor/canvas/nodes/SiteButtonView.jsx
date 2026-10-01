import { ActionIcon, Popover, SegmentedControl, Stack, Text, Tooltip } from '@mantine/core';
import { IconLink, IconTrash } from '@tabler/icons-react';
import { NodeViewWrapper } from '@tiptap/react';
import InlineText from '../../components/InlineText';
import LinkTargetInput from '../../components/LinkTargetInput';
import { useWorkspaceContext } from '../../lib/context';

export function describeHref(href, pages) {
    if (!href) return 'Aucune cible';

    if (href.startsWith('page:')) {
        const page = pages.find((candidate) => candidate.key === href.slice(5).split('#')[0]);

        return page ? `Page « ${page.nav_label} »` : 'Page supprimée';
    }

    if (href.startsWith('tel:')) return `Appel au ${href.slice(4)}`;
    if (href.startsWith('mailto:')) return `Email à ${href.slice(7)}`;

    return href;
}

/**
 * Bouton d'action placé dans le texte : libellé éditable, cible et style dans un panneau.
 */
export default function SiteButtonView({ node, updateAttributes, deleteNode, selected }) {
    const { pages } = useWorkspaceContext();
    const { label, href, variant } = node.attrs;

    return (
        <NodeViewWrapper className={`ed-block-button${selected ? ' is-selected' : ''}`}>
            <p className="rt-button" contentEditable={false}>
                <span className={`button button-${variant === 'secondary' ? 'secondary' : 'primary'}`}>
                    <InlineText value={label} onChange={(value) => updateAttributes({ label: value })} placeholder="Libellé du bouton" maxLength={60} />
                </span>
                <Popover width={280} position="bottom-start" shadow="lg" withinPortal>
                    <Popover.Target>
                        <Tooltip label={describeHref(href, pages)} withinPortal>
                            <ActionIcon variant="light" size="md" className="ed-button-link" aria-label="Cible du bouton"><IconLink size={16} /></ActionIcon>
                        </Tooltip>
                    </Popover.Target>
                    <Popover.Dropdown>
                        <Stack gap="sm">
                            <Text size="xs" fw={600}>Destination</Text>
                            <LinkTargetInput value={href} onChange={(value) => updateAttributes({ href: value })} />
                            <Text size="xs" fw={600}>Style</Text>
                            <SegmentedControl size="xs" fullWidth value={variant} onChange={(value) => updateAttributes({ variant: value })} data={[{ value: 'primary', label: 'Plein' }, { value: 'secondary', label: 'Contour' }]} />
                        </Stack>
                    </Popover.Dropdown>
                </Popover>
                <Tooltip label="Supprimer le bouton" withinPortal>
                    <ActionIcon variant="subtle" color="red" size="md" onClick={deleteNode} aria-label="Supprimer le bouton"><IconTrash size={16} /></ActionIcon>
                </Tooltip>
            </p>
        </NodeViewWrapper>
    );
}
