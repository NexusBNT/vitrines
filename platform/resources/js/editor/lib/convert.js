/**
 * Passage entre les sections d'une page (format enregistré) et le document de l'éditeur (ProseMirror).
 *
 * - Les sections du thème deviennent des nœuds « section » dont l'attribut `data` porte la section entière.
 * - Les sections « content » (blocs libres) sont déroulées dans le document ; à l'inverse, les blocs libres
 *   consécutifs sont regroupés en une section « content ».
 */
export const HEAD_TYPES = ['hero', 'page_header'];

export const nodeName = (sectionType) => `sec_${sectionType}`;

export const sectionTypeOf = (nodeType) => (nodeType?.startsWith('sec_') ? nodeType.slice(4) : null);

const isEmptyParagraph = (node) => node.type === 'paragraph' && !(node.content?.length);

export function sectionsToDoc(sections) {
    const content = [];

    for (const section of sections) {
        if (section.type === 'content') {
            content.push(...(section.blocks ?? []));
        } else {
            content.push({ type: nodeName(section.type), attrs: { data: section } });
        }
    }

    if (content.length === 0 || !HEAD_TYPES.includes(sectionTypeOf(content[0].type))) {
        content.unshift({ type: nodeName('page_header'), attrs: { data: { type: 'page_header', h1: '', lead: null } } });
    }

    // Un paragraphe final permet d'écrire après la dernière section (il n'est pas enregistré s'il reste vide).
    if (content[content.length - 1].type !== 'paragraph') {
        content.push({ type: 'paragraph' });
    }

    return { type: 'doc', content };
}

export function docToSections(doc) {
    const sections = [];
    let blocks = [];

    const flush = () => {
        while (blocks.length && isEmptyParagraph(blocks[0])) blocks.shift();
        while (blocks.length && isEmptyParagraph(blocks[blocks.length - 1])) blocks.pop();

        if (blocks.length) {
            sections.push({ type: 'content', blocks });
        }

        blocks = [];
    };

    for (const node of doc.content ?? []) {
        const type = sectionTypeOf(node.type);

        if (type) {
            flush();
            sections.push({ ...node.attrs.data, type });
        } else {
            blocks.push(node);
        }
    }

    flush();

    return sections;
}

/**
 * Paragraphes ProseMirror à partir d'un texte (paragraphes séparés par une ligne vide).
 */
export function textToParagraphs(text) {
    return text
        .split(/\n{2,}/)
        .map((paragraph) => paragraph.trim())
        .filter(Boolean)
        .map((paragraph) => ({
            type: 'paragraph',
            content: paragraph.split('\n').flatMap((line, index) => [
                ...(index > 0 ? [{ type: 'hardBreak' }] : []),
                ...(line ? [{ type: 'text', text: line }] : []),
            ]),
        }));
}

export function newPageKey() {
    return `p-${Math.random().toString(36).slice(2, 8)}`;
}

export function slugify(value) {
    return value
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 60);
}

/**
 * Chemin public d'une page (sans barre finale), calculé comme sur le serveur.
 */
export function pagePath(page, pages) {
    if (page.key === 'home') return '/';

    const segment = page.segment || slugify(page.nav_label) || 'page';
    const parent = page.parent ? pages.find((candidate) => candidate.key === page.parent) : null;

    return parent ? `${pagePath(parent, pages).replace(/\/$/, '')}/${segment}/` : `/${segment}/`;
}
