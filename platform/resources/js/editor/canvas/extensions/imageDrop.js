import { Extension } from '@tiptap/core';
import { Plugin, PluginKey } from '@tiptap/pm/state';

const ACCEPTED = ['image/jpeg', 'image/png', 'image/webp'];

/**
 * Dépôt ou collage de photos dans le texte : envoi dans la photothèque du site puis insertion à l'endroit visé.
 */
export const ImageDrop = Extension.create({
    name: 'imageDrop',

    addOptions() {
        return { upload: async () => null };
    },

    addProseMirrorPlugins() {
        const { upload } = this.options;
        const editor = this.editor;

        const insertFiles = (files, pos) => {
            files.forEach(async (file) => {
                const media = await upload(file);

                if (media) {
                    const at = Math.min(pos, editor.state.doc.content.size);
                    const $pos = editor.state.doc.resolve(at);
                    const target = $pos.depth > 0 ? $pos.after(1) : at;

                    editor.chain().insertContentAt(target, { type: 'mediaImage', attrs: { media: media.id, caption: null, size: 'normal' } }).run();
                }
            });
        };

        const imagesOf = (list) => Array.from(list ?? []).filter((file) => ACCEPTED.includes(file.type));

        return [
            new Plugin({
                key: new PluginKey('imageDrop'),
                props: {
                    handleDrop: (view, event) => {
                        const files = imagesOf(event.dataTransfer?.files);

                        if (files.length === 0) return false;

                        event.preventDefault();
                        const coordinates = view.posAtCoords({ left: event.clientX, top: event.clientY });
                        insertFiles(files, coordinates?.pos ?? view.state.selection.to);

                        return true;
                    },
                    handlePaste: (view, event) => {
                        const files = imagesOf(event.clipboardData?.files);

                        if (files.length === 0) return false;

                        insertFiles(files, view.state.selection.to);

                        return true;
                    },
                },
            }),
        ];
    },
});
