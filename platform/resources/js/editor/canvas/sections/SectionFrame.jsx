import { ActionIcon, Badge, Divider, Group, Popover, Stack, Switch, Text, TextInput, Tooltip } from '@mantine/core';
import { IconAdjustmentsHorizontal, IconTrash } from '@tabler/icons-react';
import { NodeViewWrapper } from '@tiptap/react';
import { slugify } from '../../lib/convert';
import { useWorkspaceContext } from '../../lib/context';
import { deleteBlock } from '../commands';

/**
 * Données d'une section et mise à jour partielle.
 */
export function useSection({ node, updateAttributes }) {
    const data = node.attrs.data;

    return [data, (patch) => updateAttributes({ data: { ...data, ...patch } })];
}

/**
 * Liste d'éléments d'une section (services, questions…) : ajout, suppression, déplacement.
 */
export function itemsHelpers(items, setItems) {
    const list = items ?? [];

    return {
        list,
        update: (index, patch) => setItems(list.map((item, position) => (position === index ? { ...item, ...patch } : item))),
        remove: (index) => setItems(list.filter((_, position) => position !== index)),
        add: (item) => setItems([...list, item]),
        move: (index, direction) => {
            const target = index + direction;

            if (target < 0 || target >= list.length) return;

            const next = [...list];
            [next[index], next[target]] = [next[target], next[index]];
            setItems(next);
        },
    };
}

/**
 * Cadre commun des sections : étiquette, réglages, suppression, et lien de menu pour les sites d'une page.
 */
export default function SectionFrame({ props, label, settings, children, className = '' }) {
    const { editor, getPos, selected, node } = props;
    const { site } = useWorkspaceContext();
    const [data, set] = useSection(props);
    const isHead = node.type.spec.group === 'head';
    const showMenuSettings = site.single_page && !isHead;
    const hasSettings = Boolean(settings) || showMenuSettings;

    return (
        <NodeViewWrapper className={`ed-section${selected ? ' is-selected' : ''} ${className}`} data-section={data.type}>
            <div className="ed-section-bar" contentEditable={false}>
                <Badge variant="filled" color="dark" size="sm" radius="sm" className="ed-section-label">{label}</Badge>
                {hasSettings && (
                    <Popover width={300} position="bottom-start" shadow="lg" withinPortal>
                        <Popover.Target>
                            <Tooltip label="Réglages de la section" withinPortal>
                                <ActionIcon size="sm" variant="filled" color="dark" aria-label="Réglages de la section">
                                    <IconAdjustmentsHorizontal size={14} />
                                </ActionIcon>
                            </Tooltip>
                        </Popover.Target>
                        <Popover.Dropdown>
                            <Stack gap="sm">
                                {settings}
                                {showMenuSettings && (
                                    <>
                                        {settings && <Divider />}
                                        <Switch
                                            label="Lien dans le menu"
                                            description="Le menu du site fait défiler jusqu'à cette section."
                                            checked={Boolean(data.anchor && data.nav_label)}
                                            onChange={(event) => set(event.currentTarget.checked
                                                ? { nav_label: data.heading?.slice(0, 30) || label, anchor: slugify(data.heading || label) || data.type }
                                                : { nav_label: null, anchor: data.anchor })}
                                        />
                                        {data.anchor && data.nav_label && (
                                            <TextInput
                                                size="xs"
                                                label="Libellé dans le menu"
                                                maxLength={30}
                                                value={data.nav_label}
                                                onChange={(event) => set({ nav_label: event.currentTarget.value, anchor: slugify(event.currentTarget.value) || data.anchor })}
                                            />
                                        )}
                                    </>
                                )}
                            </Stack>
                        </Popover.Dropdown>
                    </Popover>
                )}
                {!isHead && (
                    <Tooltip label="Supprimer la section" withinPortal>
                        <ActionIcon size="sm" variant="filled" color="red" onClick={() => deleteBlock(editor, getPos())} aria-label="Supprimer la section">
                            <IconTrash size={14} />
                        </ActionIcon>
                    </Tooltip>
                )}
            </div>
            <div contentEditable={false} className="ed-section-body">{children}</div>
        </NodeViewWrapper>
    );
}

export function SettingLabel({ children }) {
    return <Text size="xs" fw={600} mb={4}>{children}</Text>;
}

/**
 * Petite barre d'outils d'un élément de liste (déplacer, supprimer).
 */
export function ItemTools({ index, count, onMove, onRemove, vertical = false }) {
    return (
        <Group gap={2} className="ed-item-tools" contentEditable={false}>
            <ActionIcon size="xs" variant="default" disabled={index === 0} onClick={() => onMove(index, -1)} aria-label="Déplacer avant">
                {vertical ? '↑' : '←'}
            </ActionIcon>
            <ActionIcon size="xs" variant="default" disabled={index === count - 1} onClick={() => onMove(index, 1)} aria-label="Déplacer après">
                {vertical ? '↓' : '→'}
            </ActionIcon>
            <ActionIcon size="xs" variant="default" color="red" onClick={() => onRemove(index)} aria-label="Supprimer">
                ×
            </ActionIcon>
        </Group>
    );
}
