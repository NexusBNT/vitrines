import { computePosition, flip, offset, shift } from '@floating-ui/dom';
import { Extension } from '@tiptap/core';
import { PluginKey } from '@tiptap/pm/state';
import { ReactRenderer } from '@tiptap/react';
import Suggestion from '@tiptap/suggestion';
import SlashMenu from '../menus/SlashMenu';

const normalize = (value) => value.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

/**
 * Menu « / » : insertion de blocs libres, de sections du thème et de texte rédigé par l'IA.
 * `getItems()` fournit la liste à jour (elle dépend de l'offre du site et de la disponibilité de l'IA).
 */
export const SlashCommand = Extension.create({
    name: 'slashCommand',

    addOptions() {
        return { getItems: () => [] };
    },

    addProseMirrorPlugins() {
        return [
            Suggestion({
                editor: this.editor,
                pluginKey: new PluginKey('slashCommand'),
                char: '/',
                allowSpaces: false,
                startOfLine: false,
                allow: ({ state, range }) => {
                    const $from = state.doc.resolve(range.from);

                    return $from.parent.type.name === 'paragraph';
                },
                items: ({ query }) => {
                    const search = normalize(query);

                    return this.options.getItems().filter((item) => !search || normalize(`${item.title} ${item.keywords ?? ''}`).includes(search)).slice(0, 30);
                },
                command: ({ editor, range, props }) => {
                    editor.chain().focus().deleteRange(range).run();
                    props.run(editor, range);
                },
                render: () => {
                    let component;
                    let element;

                    const place = (clientRect) => {
                        const rect = clientRect?.();

                        if (!rect || !element) return;

                        computePosition({ getBoundingClientRect: () => rect }, element, {
                            placement: 'bottom-start',
                            strategy: 'fixed',
                            middleware: [offset(8), flip({ padding: 12 }), shift({ padding: 12 })],
                        }).then(({ x, y }) => {
                            Object.assign(element.style, { left: `${x}px`, top: `${y}px` });
                        });
                    };

                    return {
                        onStart: (props) => {
                            component = new ReactRenderer(SlashMenu, { props, editor: props.editor });
                            element = component.element;
                            element.classList.add('ed-slash-host');
                            document.body.appendChild(element);
                            place(props.clientRect);
                        },
                        onUpdate: (props) => {
                            component?.updateProps(props);
                            place(props.clientRect);
                        },
                        onKeyDown: (props) => {
                            if (props.event.key === 'Escape') {
                                element?.remove();

                                return true;
                            }

                            return component?.ref?.onKeyDown(props.event) ?? false;
                        },
                        onExit: () => {
                            element?.remove();
                            component?.destroy();
                            component = null;
                            element = null;
                        },
                    };
                },
            }),
        ];
    },
});
