import { NodeSelection, TextSelection } from '@tiptap/pm/state';
import { nodeName } from '../lib/convert';

/**
 * Insère du contenu au premier niveau du document, après le bloc qui contient le curseur
 * (ou à sa place si ce bloc est un paragraphe vide). Les sections du thème ne peuvent pas être imbriquées.
 */
export function insertTopLevel(editor, content) {
    const { state } = editor;
    const { $from } = state.selection;

    if ($from.depth === 0) {
        return editor.chain().focus().insertContentAt(state.selection.from, content).run();
    }

    const start = $from.before(1);
    const end = $from.after(1);
    const block = state.doc.nodeAt(start);
    const isEmptyParagraph = block?.type.name === 'paragraph' && block.content.size === 0;

    return editor
        .chain()
        .focus()
        .insertContentAt(isEmptyParagraph ? { from: start, to: end } : end, content)
        .run();
}

export function insertSection(editor, type, data) {
    return insertTopLevel(editor, { type: nodeName(type), attrs: { data: { type, ...data } } });
}

/**
 * Contenu initial des sections insérées depuis le menu « / ».
 */
export function defaultSection(type, site) {
    switch (type) {
        case 'services':
            return { layout: 'cards', heading: 'Nos services', intro: null, items: [{ name: 'Nouveau service', text: '', image: null }], link: null };
        case 'about':
            return { heading: `À propos de ${site.name}`, paragraphs: [''], image: null, link: null };
        case 'highlights':
            return { heading: 'Nos points forts', items: [{ title: '', text: '' }, { title: '', text: '' }, { title: '', text: '' }] };
        case 'gallery':
            return { heading: 'Nos réalisations', intro: null, images: [], link: null };
        case 'zone':
            return { heading: 'Zone d\'intervention', text: '', towns: [site.city, ...(site.service_area ?? [])].filter(Boolean) };
        case 'faq':
            return { heading: 'Questions fréquentes', items: [{ question: '', answer: '' }] };
        case 'cta':
            return { heading: 'Un projet ? Parlons-en', text: '' };
        case 'contact':
            return { heading: 'Nous contacter', text: '', show_map: false };
        default:
            return {};
    }
}

/**
 * Opérations sur un bloc de premier niveau (menu de la poignée).
 */
export function topLevelIndex(state, pos) {
    return state.doc.resolve(pos).index(0);
}

export function moveBlock(editor, pos, direction) {
    const { state } = editor;
    const node = state.doc.nodeAt(pos);
    const index = topLevelIndex(state, pos);
    const targetIndex = index + direction;

    // L'en-tête reste toujours en première position.
    if (!node || targetIndex < 1 || targetIndex >= state.doc.childCount) return false;

    const sibling = state.doc.child(targetIndex);
    const tr = state.tr;

    if (direction < 0) {
        const siblingPos = pos - sibling.nodeSize;
        tr.delete(pos, pos + node.nodeSize).insert(siblingPos, node);
        tr.setSelection(NodeSelection.create(tr.doc, siblingPos));
    } else {
        const siblingEnd = pos + node.nodeSize + sibling.nodeSize;
        tr.insert(siblingEnd, node).delete(pos, pos + node.nodeSize);
        tr.setSelection(NodeSelection.create(tr.doc, pos + sibling.nodeSize));
    }

    editor.view.dispatch(tr.scrollIntoView());

    return true;
}

export function duplicateBlock(editor, pos) {
    const node = editor.state.doc.nodeAt(pos);

    if (!node) return false;

    return editor.chain().insertContentAt(pos + node.nodeSize, node.toJSON()).run();
}

export function deleteBlock(editor, pos) {
    const node = editor.state.doc.nodeAt(pos);

    if (!node || topLevelIndex(editor.state, pos) === 0) return false;

    editor.view.dispatch(editor.state.tr.delete(pos, pos + node.nodeSize));

    return true;
}

/**
 * Place le curseur dans un bloc de texte pour pouvoir le transformer (titre, liste…).
 */
export function focusTextBlock(editor, pos) {
    const tr = editor.state.tr.setSelection(TextSelection.near(editor.state.doc.resolve(pos + 1)));
    editor.view.dispatch(tr);
    editor.view.focus();

    return editor.chain().focus();
}
