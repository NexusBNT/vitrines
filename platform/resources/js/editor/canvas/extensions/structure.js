import { Extension, mergeAttributes, Node } from '@tiptap/core';
import { NodeSelection, Plugin, PluginKey, TextSelection } from '@tiptap/pm/state';
import Document from '@tiptap/extension-document';
import { ReactNodeViewRenderer } from '@tiptap/react';
import { nodeName } from '../../lib/convert';

/**
 * Une page commence toujours par un seul en-tête (bandeau ou titre de page), suivi de sections du thème
 * et de blocs libres dans n'importe quel ordre. Le schéma l'impose : l'en-tête ne peut être ni supprimé ni déplacé.
 */
export const PageDocument = Document.extend({
    content: 'head (section | block | layout)*',
});

/**
 * Section du thème : un nœud atomique dont l'attribut `data` porte toute la section, éditée dans sa vue React.
 */
export function createSectionNode(type, component, { head = false } = {}) {
    return Node.create({
        name: nodeName(type),
        group: head ? 'head' : 'section',
        atom: true,
        isolating: true,
        draggable: !head,
        selectable: !head,

        addAttributes() {
            return {
                data: {
                    default: { type },
                    parseHTML: (element) => {
                        try {
                            return JSON.parse(element.getAttribute('data-section') ?? '{}');
                        } catch {
                            return { type };
                        }
                    },
                    renderHTML: (attributes) => ({ 'data-section': JSON.stringify(attributes.data) }),
                },
            };
        },

        parseHTML() {
            return [{ tag: `div[data-section-type="${type}"]` }];
        },

        renderHTML({ HTMLAttributes }) {
            return ['div', mergeAttributes(HTMLAttributes, { 'data-section-type': type })];
        },

        addNodeView() {
            return ReactNodeViewRenderer(component, { className: `ed-node ed-node--section${head ? ' ed-node--head' : ''}` });
        },
    });
}

/**
 * Encadré (information, succès, attention) contenant du texte et des listes.
 */
export const Callout = Node.create({
    name: 'callout',
    group: 'block',
    content: '(paragraph | bulletList | orderedList)+',
    defining: true,

    addAttributes() {
        return {
            tone: {
                default: 'info',
                parseHTML: (element) => element.getAttribute('data-tone') ?? 'info',
                renderHTML: (attributes) => ({ 'data-tone': attributes.tone }),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'aside[data-callout]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['aside', mergeAttributes(HTMLAttributes, { 'data-callout': '', class: `rt-callout rt-callout--${HTMLAttributes['data-tone'] ?? 'info'}` }), 0];
    },
});

/**
 * Colonnes (2 ou 3), chacune contenant des blocs libres.
 */
export const Columns = Node.create({
    name: 'columns',
    group: 'layout',
    content: 'column{2,3}',
    isolating: true,
    draggable: true,

    parseHTML() {
        return [{ tag: 'div[data-columns]' }];
    },

    renderHTML({ node, HTMLAttributes }) {
        return ['div', mergeAttributes(HTMLAttributes, { 'data-columns': '', class: `rt-columns rt-columns--${node.childCount}` }), 0];
    },
});

export const Column = Node.create({
    name: 'column',
    content: 'block+',
    isolating: true,

    parseHTML() {
        return [{ tag: 'div[data-column]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['div', mergeAttributes(HTMLAttributes, { 'data-column': '', class: 'rt-column' }), 0];
    },
});

export function columnsContent(count) {
    return {
        type: 'columns',
        content: Array.from({ length: count }, () => ({ type: 'column', content: [{ type: 'paragraph' }] })),
    };
}

/**
 * Image du site (choisie dans la photothèque), avec légende et largeur.
 */
export function createMediaImage(component) {
    return Node.create({
        name: 'mediaImage',
        group: 'block',
        atom: true,
        draggable: true,

        addAttributes() {
            return {
                media: { default: null },
                caption: { default: null },
                size: { default: 'normal' },
            };
        },

        parseHTML() {
            return [{
                tag: 'figure[data-media]',
                getAttrs: (element) => ({
                    media: Number(element.getAttribute('data-media')) || null,
                    caption: element.getAttribute('data-caption'),
                    size: element.getAttribute('data-size') ?? 'normal',
                }),
            }];
        },

        renderHTML({ node }) {
            return ['figure', { 'data-media': node.attrs.media ?? '', 'data-caption': node.attrs.caption ?? '', 'data-size': node.attrs.size }];
        },

        addNodeView() {
            return ReactNodeViewRenderer(component, { className: 'ed-node ed-node--media' });
        },
    });
}

/**
 * Bouton d'action (vers une page du site, une adresse web, un téléphone ou un email).
 */
export function createSiteButton(component) {
    return Node.create({
        name: 'siteButton',
        group: 'block',
        atom: true,
        draggable: true,

        addAttributes() {
            return {
                label: { default: 'Nous contacter' },
                href: { default: 'page:contact' },
                variant: { default: 'primary' },
            };
        },

        parseHTML() {
            return [{
                tag: 'p[data-site-button]',
                getAttrs: (element) => ({
                    label: element.getAttribute('data-label'),
                    href: element.getAttribute('data-href'),
                    variant: element.getAttribute('data-variant') ?? 'primary',
                }),
            }];
        },

        renderHTML({ node }) {
            return ['p', { 'data-site-button': '', 'data-label': node.attrs.label, 'data-href': node.attrs.href, 'data-variant': node.attrs.variant }];
        },

        addNodeView() {
            return ReactNodeViewRenderer(component, { className: 'ed-node ed-node--button' });
        },
    });
}

/**
 * Protège les sections du thème : une frappe, Retour arrière ou Suppr alors qu'une section est sélectionnée
 * ne la remplace pas (suppression uniquement par ses boutons). Entrée ajoute un paragraphe après la section,
 * et un collage se fait après elle.
 */
export const SectionGuard = Extension.create({
    name: 'sectionGuard',

    addProseMirrorPlugins() {
        const isSectionSelection = (state) => state.selection instanceof NodeSelection && ['section', 'head'].includes(state.selection.node.type.spec.group);

        const moveAfterSection = (view, withParagraph) => {
            const { state } = view;
            const end = state.selection.to;
            let tr = state.tr;

            if (withParagraph) {
                tr = tr.insert(end, state.schema.nodes.paragraph.create());
            }

            view.dispatch(tr.setSelection(TextSelection.near(tr.doc.resolve(withParagraph ? end + 1 : end))).scrollIntoView());
        };

        return [
            new Plugin({
                key: new PluginKey('sectionGuard'),
                props: {
                    handleTextInput: (view) => isSectionSelection(view.state),
                    handleKeyDown: (view, event) => {
                        if (!isSectionSelection(view.state)) return false;

                        if (event.key === 'Enter') {
                            moveAfterSection(view, true);

                            return true;
                        }

                        return ['Backspace', 'Delete'].includes(event.key) || (event.key.length === 1 && !event.metaKey && !event.ctrlKey);
                    },
                    handlePaste: (view) => {
                        if (isSectionSelection(view.state)) {
                            moveAfterSection(view, true);
                        }

                        return false;
                    },
                },
            }),
        ];
    },
});
