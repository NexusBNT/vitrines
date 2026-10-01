import StarterKit from '@tiptap/starter-kit';
import { Placeholder } from '@tiptap/extensions';
import { EditorContent, useEditor } from '@tiptap/react';
import { memo, useEffect, useMemo, useRef } from 'react';
import { docToSections, HEAD_TYPES, sectionsToDoc } from '../lib/convert';
import { useWorkspaceContext } from '../lib/context';
import { ImageDrop } from './extensions/imageDrop';
import { SlashCommand } from './extensions/slash';
import { Callout, Column, Columns, createMediaImage, createSectionNode, createSiteButton, PageDocument, SectionGuard } from './extensions/structure';
import BlockHandle from './menus/BlockHandle';
import BubbleToolbar from './menus/BubbleToolbar';
import MediaImageView from './nodes/MediaImageView';
import SiteButtonView from './nodes/SiteButtonView';
import { SECTION_VIEWS } from './sections/views';
import { buildSlashItems } from './slashItems';

const SECTION_NODES = Object.entries(SECTION_VIEWS).map(([type, view]) => createSectionNode(type, view, { head: HEAD_TYPES.includes(type) }));
const MEDIA_IMAGE = createMediaImage(MediaImageView);
const SITE_BUTTON = createSiteButton(SiteButtonView);

/**
 * Canevas d'une page : un document TipTap mis en forme avec les styles du site.
 * Monté une fois par page (clé = page) ; chaque modification remonte les sections à jour.
 */
function PageCanvas({ page, onChange, onEditorReady }) {
    const context = useWorkspaceContext();
    const itemsRef = useRef([]);
    const uploadRef = useRef(context.uploadMedia);
    const onChangeRef = useRef(onChange);
    onChangeRef.current = onChange;
    uploadRef.current = context.uploadMedia;

    itemsRef.current = useMemo(() => buildSlashItems({
        site: context.site,
        sectionLabels: context.sectionLabels,
        aiAvailable: context.aiAvailable,
        pickMedia: context.pickMedia,
        openAiWrite: context.openAiWrite,
    }), [context.site, context.sectionLabels, context.aiAvailable, context.pickMedia, context.openAiWrite]);

    const editor = useEditor({
        extensions: [
            PageDocument,
            StarterKit.configure({
                document: false,
                code: false,
                codeBlock: false,
                heading: { levels: [2, 3] },
                link: {
                    openOnClick: false,
                    autolink: true,
                    defaultProtocol: 'https',
                    isAllowedUri: (url, { defaultValidate }) => /^page:[a-z0-9-]+(#[a-z0-9-]+)?$/.test(url) || defaultValidate(url),
                },
            }),
            Placeholder.configure({
                placeholder: ({ node }) => (node.type.name === 'heading' ? 'Intertitre' : 'Écrivez, ou tapez « / » pour insérer un bloc, une section ou l\'IA…'),
                includeChildren: true,
            }),
            Callout,
            Columns,
            Column,
            MEDIA_IMAGE,
            SITE_BUTTON,
            ...SECTION_NODES,
            SectionGuard,
            SlashCommand.configure({ getItems: () => itemsRef.current }),
            ImageDrop.configure({ upload: (file) => uploadRef.current(file) }),
        ],
        content: sectionsToDoc(page.sections),
        editorProps: {
            attributes: { class: 'ed-prose', spellcheck: 'true', 'aria-label': `Contenu de la page ${page.nav_label}` },
        },
        immediatelyRender: true,
        shouldRerenderOnTransaction: false,
        onCreate: ({ editor: current }) => {
            // Curseur en fin de page plutôt qu'une section sélectionnée (invisible) au chargement.
            current.commands.setTextSelection(current.state.doc.content.size - 1);
        },
        onUpdate: ({ editor: current }) => {
            onChangeRef.current(docToSections(current.getJSON()));
        },
    });

    useEffect(() => {
        onEditorReady?.(editor);

        return () => onEditorReady?.(null);
    }, [editor, onEditorReady]);

    return (
        <div className="ed-canvas-page">
            <EditorContent editor={editor} />
            <BlockHandle editor={editor} />
            <BubbleToolbar editor={editor} />
        </div>
    );
}

export default memo(PageCanvas, (previous, next) => previous.page.key === next.page.key);
