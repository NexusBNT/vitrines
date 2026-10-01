/**
 * Arborescence des pages : l'ordre du tableau est celui du menu (chaque page suivie de ses sous-pages).
 */
export function toTree(pages) {
    const top = pages.filter((page) => !page.parent);

    return top.map((page) => ({
        id: page.key,
        page,
        children: pages.filter((child) => child.parent === page.key).map((child) => ({ id: child.key, page: child, children: null })),
    }));
}

function flatten(tree) {
    return tree.flatMap((node) => [
        { ...node.page, parent: null },
        ...(node.children ?? []).map((child) => ({ ...child.page, parent: node.id })),
    ]);
}

/**
 * Déplace une page (glisser-déposer). `index` suit la convention de react-arborist :
 * position parmi les enfants du nouveau parent, avant le retrait de la page déplacée.
 */
export function movePage(pages, key, parentKey, index) {
    const tree = toTree(pages).map((node) => ({ ...node, children: node.children ? [...node.children] : [] }));
    const find = (id) => tree.find((node) => node.id === id) ?? tree.flatMap((node) => node.children).find((node) => node.id === id);
    const source = find(key);
    const siblings = parentKey ? find(parentKey).children : tree;
    const moved = { ...source, children: parentKey ? null : source.children ?? [] };

    siblings.splice(index, 0, moved);

    const removeFrom = (list) => {
        const position = list.findIndex((node) => node === source);

        if (position !== -1) list.splice(position, 1);
    };

    removeFrom(tree);
    tree.forEach((node) => removeFrom(node.children));

    return flatten(tree);
}

/**
 * Ajoute une page juste après son parent (et ses sous-pages existantes), ou à la fin.
 */
export function insertPage(pages, page) {
    if (!page.parent) return [...pages, page];

    const lastIndex = pages.reduce((last, candidate, index) => (candidate.key === page.parent || candidate.parent === page.parent ? index : last), -1);
    const next = [...pages];
    next.splice(lastIndex + 1, 0, page);

    return next;
}

/**
 * Supprime une page ; ses sous-pages remontent au premier niveau.
 */
export function removePage(pages, key) {
    return pages.filter((page) => page.key !== key).map((page) => (page.parent === key ? { ...page, parent: null } : page));
}
