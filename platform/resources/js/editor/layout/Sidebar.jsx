import { ActionIcon, Badge, Button, Group, Menu, Text, Tooltip, UnstyledButton } from '@mantine/core';
import { useElementSize } from '@mantine/hooks';
import {
    IconArrowLeft,
    IconChevronRight,
    IconDots,
    IconEye,
    IconEyeOff,
    IconFile,
    IconHome,
    IconPencil,
    IconPhoto,
    IconPlus,
    IconSettings,
    IconTrash,
} from '@tabler/icons-react';
import { createContext, memo, useContext, useMemo } from 'react';
import { Tree } from 'react-arborist';
import { toTree } from '../lib/tree';

const ROW_HEIGHT = 34;

const RowContext = createContext(null);

function Row({ node, style, dragHandle }) {
    const { currentKey, protectedPages, canAdd, onSelect, onAddPage, onDeletePage, onToggleNav, onOpenSettings } = useContext(RowContext);
    const page = node.data.page;
    const isTop = !page.parent;
    const isProtected = protectedPages.includes(page.key);

    return (
        <div
            style={style}
            ref={dragHandle}
            className={`ed-tree-row${page.key === currentKey ? ' is-active' : ''}${node.willReceiveDrop ? ' is-drop' : ''}`}
            onClick={() => onSelect(page.key)}
        >
            <span className="ed-tree-toggle" onClick={(event) => { event.stopPropagation(); node.toggle(); }}>
                {node.children?.length ? <IconChevronRight size={14} className={node.isOpen ? 'is-open' : ''} /> : null}
            </span>
            {page.key === 'home' ? <IconHome size={16} className="ed-tree-icon" /> : <IconFile size={16} className="ed-tree-icon" />}
            {node.isEditing ? (
                <input
                    className="ed-tree-input"
                    autoFocus
                    defaultValue={page.nav_label}
                    maxLength={30}
                    onClick={(event) => event.stopPropagation()}
                    onBlur={(event) => node.submit(event.currentTarget.value)}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') node.submit(event.currentTarget.value);
                        if (event.key === 'Escape') node.reset();
                    }}
                />
            ) : (
                <span className="ed-tree-label">{page.nav_label}</span>
            )}
            {page.key !== 'home' && page.in_nav === false && (
                <Tooltip label="Absente du menu">
                    <IconEyeOff size={14} className="ed-tree-hidden" />
                </Tooltip>
            )}
            <Menu position="bottom-end" withinPortal shadow="md" width={230}>
                <Menu.Target>
                    <ActionIcon variant="subtle" color="gray" size="sm" className="ed-tree-menu" onClick={(event) => event.stopPropagation()} aria-label="Actions de la page">
                        <IconDots size={16} />
                    </ActionIcon>
                </Menu.Target>
                <Menu.Dropdown onClick={(event) => event.stopPropagation()}>
                    <Menu.Item leftSection={<IconPencil size={16} />} onClick={() => node.edit()}>Renommer</Menu.Item>
                    <Menu.Item leftSection={<IconSettings size={16} />} onClick={() => { onSelect(page.key); onOpenSettings(); }}>Réglages et référencement</Menu.Item>
                    {isTop && page.key !== 'home' && (
                        <Menu.Item leftSection={<IconPlus size={16} />} disabled={!canAdd} onClick={() => onAddPage(page.key)}>Ajouter une sous-page</Menu.Item>
                    )}
                    {page.key !== 'home' && (
                        <Menu.Item leftSection={page.in_nav === false ? <IconEye size={16} /> : <IconEyeOff size={16} />} onClick={() => onToggleNav(page.key)}>
                            {page.in_nav === false ? 'Afficher dans le menu' : 'Masquer du menu'}
                        </Menu.Item>
                    )}
                    {!isProtected && (
                        <>
                            <Menu.Divider />
                            <Menu.Item color="red" leftSection={<IconTrash size={16} />} onClick={() => onDeletePage(page.key)}>Supprimer</Menu.Item>
                        </>
                    )}
                </Menu.Dropdown>
            </Menu>
        </div>
    );
}


function Sidebar({ site, urls, pages, currentKey, protectedPages, onSelect, onMove, onRename, onAddPage, onDeletePage, onToggleNav, onOpenSettings, onOpenMedia }) {
    const { ref, height } = useElementSize();
    const data = useMemo(() => toTree(pages), [pages]);
    const canAdd = pages.length < site.max_pages;

    const disableDrop = ({ parentNode, dragNodes, index }) => {
        if (parentNode.isRoot) return index === 0;

        const hasChildren = dragNodes.some((node) => node.children?.length);

        return parentNode.level > 0 || parentNode.id === 'home' || hasChildren;
    };

    return (
        <aside className="ed-sidebar">
            <div className="ed-sidebar-head">
                <UnstyledButton component="a" href={urls.back} className="ed-back">
                    <IconArrowLeft size={16} />
                    <span>Paramètres du site</span>
                </UnstyledButton>
                <Text fw={700} size="lg" lineClamp={1} mt={6}>{site.name}</Text>
                <Text size="xs" c="dimmed" lineClamp={1}>{site.activity} · {site.city}</Text>
            </div>

            <Group justify="space-between" px="md" pt="md" pb={6}>
                <Text size="xs" fw={700} tt="uppercase" c="dimmed">Pages</Text>
                <Badge size="xs" variant="light" color={canAdd ? 'gray' : 'orange'}>{pages.length} / {site.max_pages}</Badge>
            </Group>

            <div className="ed-tree" ref={ref}>
                <RowContext.Provider value={{ currentKey, protectedPages, canAdd, onSelect, onAddPage, onDeletePage, onToggleNav, onOpenSettings }}>
                {height > 0 && (
                    <Tree
                        data={data}
                        width="100%"
                        height={height}
                        rowHeight={ROW_HEIGHT}
                        indent={18}
                        openByDefault
                        disableMultiSelection
                        disableDrag={(item) => item.id === 'home'}
                        disableDrop={disableDrop}
                        onMove={({ dragIds, parentId, index }) => onMove(dragIds[0], parentId, index)}
                        onRename={({ id, name }) => onRename(id, name)}
                        rowClassName="ed-tree-row-outer"
                    >
                        {Row}
                    </Tree>
                )}
                </RowContext.Provider>
            </div>

            <div className="ed-sidebar-foot">
                <Tooltip label={site.single_page ? 'L\'offre de ce site comprend une seule page' : `Limite de l'offre : ${site.max_pages} pages`} disabled={canAdd}>
                    <Button fullWidth variant="light" leftSection={<IconPlus size={16} />} disabled={!canAdd} onClick={() => onAddPage(null)}>
                        Nouvelle page
                    </Button>
                </Tooltip>
                <Button fullWidth variant="subtle" color="gray" leftSection={<IconPhoto size={16} />} onClick={onOpenMedia} mt={6}>
                    Photos du site
                </Button>
            </div>
        </aside>
    );
}

export default memo(Sidebar);
