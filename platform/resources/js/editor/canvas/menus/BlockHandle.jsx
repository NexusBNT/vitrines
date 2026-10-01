import { ActionIcon, Menu, Tooltip } from '@mantine/core';
import {
    IconAlertSquareRounded,
    IconArrowDown,
    IconArrowUp,
    IconBlockquote,
    IconCopy,
    IconGripVertical,
    IconH2,
    IconH3,
    IconList,
    IconListNumbers,
    IconPlus,
    IconPilcrow,
    IconTrash,
} from '@tabler/icons-react';
import DragHandle from '@tiptap/extension-drag-handle-react';
import { useRef, useState } from 'react';
import { sectionTypeOf } from '../../lib/convert';
import { deleteBlock, duplicateBlock, focusTextBlock, moveBlock, topLevelIndex } from '../commands';

const TEXT_BLOCKS = ['paragraph', 'heading', 'bulletList', 'orderedList', 'blockquote'];

/**
 * Poignée à gauche de chaque bloc : « + » pour insérer dessous, glisser pour déplacer, clic pour le menu du bloc.
 */
export default function BlockHandle({ editor }) {
    const [current, setCurrent] = useState({ node: null, pos: -1 });
    const [menuOpen, setMenuOpen] = useState(false);
    const currentRef = useRef(current);
    const menuOpenRef = useRef(false);

    const onNodeChange = ({ node, pos }) => {
        if (menuOpenRef.current) return;

        currentRef.current = { node, pos };
        setCurrent({ node, pos });
    };

    const isHead = current.node?.type.spec.group === 'head';
    const isText = TEXT_BLOCKS.includes(current.node?.type.name);
    const isCallout = current.node?.type.name === 'callout';
    const index = current.pos >= 0 ? topLevelIndex(editor.state, current.pos) : 0;

    const insertBelow = () => {
        const { node, pos } = currentRef.current;

        if (!node) return;

        const end = pos + node.nodeSize;
        editor.chain().insertContentAt(end, { type: 'paragraph', content: [{ type: 'text', text: '/' }] }).setTextSelection(end + 2).focus().run();
    };

    const transform = (run) => {
        run(focusTextBlock(editor, currentRef.current.pos)).run();
        toggleMenu(false);
    };

    const toggleMenu = (opened) => {
        setMenuOpen(opened);
        menuOpenRef.current = opened;
        editor.commands.setMeta('lockDragHandle', opened);
    };

    return (
        <DragHandle editor={editor} onNodeChange={onNodeChange} className={`ed-handle${isHead ? ' is-head' : ''}`}>
            <Tooltip label="Insérer un bloc dessous" position="left" withinPortal openDelay={400}>
                <ActionIcon variant="subtle" color="gray" size="sm" onClick={insertBelow} aria-label="Insérer un bloc">
                    <IconPlus size={16} />
                </ActionIcon>
            </Tooltip>
            {!isHead && (
                <Menu opened={menuOpen} onChange={toggleMenu} position="left-start" shadow="md" width={220} withinPortal>
                    <Menu.Target>
                        <ActionIcon variant="subtle" color="gray" size="sm" className="ed-grip" aria-label="Déplacer ou modifier le bloc">
                            <IconGripVertical size={16} />
                        </ActionIcon>
                    </Menu.Target>
                    <Menu.Dropdown>
                        {current.node && sectionTypeOf(current.node.type.name) && <Menu.Label>Section</Menu.Label>}
                        {isText && (
                            <>
                                <Menu.Label>Transformer en</Menu.Label>
                                <Menu.Item leftSection={<IconPilcrow size={16} />} onClick={() => transform((chain) => chain.setParagraph())}>Texte</Menu.Item>
                                <Menu.Item leftSection={<IconH2 size={16} />} onClick={() => transform((chain) => chain.setHeading({ level: 2 }))}>Intertitre</Menu.Item>
                                <Menu.Item leftSection={<IconH3 size={16} />} onClick={() => transform((chain) => chain.setHeading({ level: 3 }))}>Sous-titre</Menu.Item>
                                <Menu.Item leftSection={<IconList size={16} />} onClick={() => transform((chain) => chain.toggleBulletList())}>Liste à puces</Menu.Item>
                                <Menu.Item leftSection={<IconListNumbers size={16} />} onClick={() => transform((chain) => chain.toggleOrderedList())}>Liste numérotée</Menu.Item>
                                <Menu.Item leftSection={<IconBlockquote size={16} />} onClick={() => transform((chain) => chain.toggleBlockquote())}>Citation</Menu.Item>
                                <Menu.Divider />
                            </>
                        )}
                        {isCallout && (
                            <>
                                <Menu.Label>Style de l'encadré</Menu.Label>
                                {[['info', 'Information'], ['success', 'Succès'], ['warning', 'Attention']].map(([tone, label]) => (
                                    <Menu.Item
                                        key={tone}
                                        leftSection={<IconAlertSquareRounded size={16} />}
                                        onClick={() => editor.view.dispatch(editor.state.tr.setNodeMarkup(currentRef.current.pos, undefined, { tone }))}
                                    >
                                        {label}
                                    </Menu.Item>
                                ))}
                                <Menu.Divider />
                            </>
                        )}
                        <Menu.Item leftSection={<IconArrowUp size={16} />} disabled={index <= 1} onClick={() => moveBlock(editor, currentRef.current.pos, -1)}>Monter</Menu.Item>
                        <Menu.Item leftSection={<IconArrowDown size={16} />} disabled={index >= editor.state.doc.childCount - 1} onClick={() => moveBlock(editor, currentRef.current.pos, 1)}>Descendre</Menu.Item>
                        <Menu.Item leftSection={<IconCopy size={16} />} onClick={() => duplicateBlock(editor, currentRef.current.pos)}>Dupliquer</Menu.Item>
                        <Menu.Divider />
                        <Menu.Item color="red" leftSection={<IconTrash size={16} />} onClick={() => deleteBlock(editor, currentRef.current.pos)}>Supprimer</Menu.Item>
                    </Menu.Dropdown>
                </Menu>
            )}
        </DragHandle>
    );
}
