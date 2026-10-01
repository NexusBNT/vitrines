import {
    IconAddressBook,
    IconAlertSquareRounded,
    IconBlockquote,
    IconChecklist,
    IconClick,
    IconColumns2,
    IconColumns3,
    IconH2,
    IconH3,
    IconHelpCircle,
    IconLayoutGrid,
    IconList,
    IconListNumbers,
    IconMapPin,
    IconMessage2,
    IconPhoto,
    IconSeparator,
    IconSparkles,
    IconSpeakerphone,
    IconStar,
    IconPilcrow,
    IconUserCircle,
} from '@tabler/icons-react';
import { columnsContent } from './extensions/structure';
import { defaultSection, insertSection, insertTopLevel } from './commands';

const TEXT = 'Texte';
const SECTIONS = 'Sections du site';
const AI = 'Intelligence artificielle';

/**
 * Entrées du menu « / ». Chaque entrée reçoit l'éditeur, une fois le « /… » saisi effacé.
 */
export function buildSlashItems({ site, sectionLabels, aiAvailable, pickMedia, openAiWrite }) {
    const section = (type, icon, description, keywords = '') => ({
        id: `section-${type}`,
        group: SECTIONS,
        title: sectionLabels[type],
        description,
        keywords,
        icon,
        run: (editor) => insertSection(editor, type, defaultSection(type, site)),
    });

    return [
        { id: 'paragraph', group: TEXT, title: 'Texte', description: 'Paragraphe simple', keywords: 'paragraphe', icon: IconPilcrow, run: (editor) => editor.chain().focus().setParagraph().run() },
        { id: 'h2', group: TEXT, title: 'Intertitre', description: 'Titre de partie (H2)', keywords: 'titre heading h2', icon: IconH2, run: (editor) => editor.chain().focus().setHeading({ level: 2 }).run() },
        { id: 'h3', group: TEXT, title: 'Sous-titre', description: 'Titre secondaire (H3)', keywords: 'titre heading h3', icon: IconH3, run: (editor) => editor.chain().focus().setHeading({ level: 3 }).run() },
        { id: 'bullets', group: TEXT, title: 'Liste à puces', description: 'Énumération simple', keywords: 'liste puces ul', icon: IconList, run: (editor) => editor.chain().focus().toggleBulletList().run() },
        { id: 'numbers', group: TEXT, title: 'Liste numérotée', description: 'Étapes, ordre', keywords: 'liste numerotee etapes ol', icon: IconListNumbers, run: (editor) => editor.chain().focus().toggleOrderedList().run() },
        { id: 'quote', group: TEXT, title: 'Citation', description: 'Mise en avant d\'une phrase', keywords: 'citation quote', icon: IconBlockquote, run: (editor) => editor.chain().focus().toggleBlockquote().run() },
        {
            id: 'callout', group: TEXT, title: 'Encadré', description: 'Information importante', keywords: 'encadre callout note info attention', icon: IconAlertSquareRounded,
            run: (editor) => editor.chain().focus().wrapIn('callout').run(),
        },
        { id: 'divider', group: TEXT, title: 'Séparateur', description: 'Ligne horizontale', keywords: 'separateur ligne hr', icon: IconSeparator, run: (editor) => editor.chain().focus().setHorizontalRule().run() },
        {
            id: 'image', group: TEXT, title: 'Image', description: 'Photo de la photothèque', keywords: 'image photo illustration', icon: IconPhoto,
            run: async (editor) => {
                const selected = await pickMedia({ title: 'Insérer une image' });

                if (selected) insertTopLevel(editor, { type: 'mediaImage', attrs: { media: selected, caption: null, size: 'normal' } });
            },
        },
        {
            id: 'button', group: TEXT, title: 'Bouton', description: 'Lien d\'action mis en avant', keywords: 'bouton lien cta', icon: IconClick,
            run: (editor) => insertTopLevel(editor, { type: 'siteButton', attrs: { label: 'Nous contacter', href: 'page:contact', variant: 'primary' } }),
        },
        { id: 'columns2', group: TEXT, title: '2 colonnes', description: 'Contenu côte à côte', keywords: 'colonnes grille', icon: IconColumns2, run: (editor) => insertTopLevel(editor, columnsContent(2)) },
        { id: 'columns3', group: TEXT, title: '3 colonnes', description: 'Contenu côte à côte', keywords: 'colonnes grille', icon: IconColumns3, run: (editor) => insertTopLevel(editor, columnsContent(3)) },

        section('services', IconLayoutGrid, 'Cartes ou liste détaillée', 'prestations'),
        section('about', IconUserCircle, 'Texte et photo côte à côte', 'a propos presentation histoire'),
        section('highlights', IconStar, 'Arguments en quelques mots', 'atouts engagements'),
        ...(site.gallery ? [section('gallery', IconPhoto, 'Photos des réalisations', 'photos realisations portfolio')] : []),
        section('zone', IconMapPin, 'Communes desservies', 'zone communes villes secteur'),
        section('faq', IconHelpCircle, 'Questions et réponses', 'questions faq'),
        section('cta', IconSpeakerphone, 'Bandeau d\'appel à l\'action', 'appel action contact telephone'),
        section('contact', IconAddressBook, 'Formulaire et coordonnées', 'formulaire coordonnees horaires'),

        ...(aiAvailable ? [{
            id: 'ai-write', group: AI, title: 'Rédiger avec l\'IA', description: 'Un passage à partir d\'une consigne', keywords: 'ia ai rediger ecrire generer', icon: IconSparkles, accent: true,
            run: (editor) => openAiWrite(editor),
        }, {
            id: 'ai-steps', group: AI, title: 'Étapes d\'une intervention', description: 'Rédigé par l\'IA à partir du brief', keywords: 'ia etapes deroulement', icon: IconChecklist, accent: true,
            run: (editor) => openAiWrite(editor, 'Décris les étapes d\'une intervention type, du premier contact à la fin du chantier, sans inventer de délai ni de prix.'),
        }, {
            id: 'ai-commitments', group: AI, title: 'Nos engagements', description: 'Rédigé par l\'IA à partir du brief', keywords: 'ia engagements valeurs', icon: IconMessage2, accent: true,
            run: (editor) => openAiWrite(editor, 'Rédige un court passage sur nos engagements envers les clients, uniquement à partir des informations fournies.'),
        }] : []),
    ];
}
