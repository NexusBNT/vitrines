import { ActionIcon, Button, SegmentedControl, Select, Switch, Text } from '@mantine/core';
import { IconArrowsExchange, IconPhotoPlus, IconPlus, IconX } from '@tabler/icons-react';
import { useState } from 'react';
import { nodeName } from '../../lib/convert';
import { useWorkspaceContext } from '../../lib/context';
import InlineText from '../../components/InlineText';
import MediaSlot from '../../components/MediaSlot';
import SectionFrame, { ItemTools, itemsHelpers, SettingLabel, useSection } from './SectionFrame';

const LAYOUT_LABELS = {
    split: 'Texte à gauche, photo à droite',
    split_reverse: 'Photo à gauche, texte à droite',
    image: 'Photo plein écran',
    boxed: 'Photo plein écran, texte encadré',
    stacked: 'Titre centré, grande photo dessous',
    plain: 'Texte seul',
};
const BACKGROUND_LAYOUTS = ['image', 'boxed'];
const MEDIA_LAYOUTS = ['split', 'split_reverse', 'stacked'];

/* ------------------------------------------------------------------ */
/* Éléments communs                                                    */
/* ------------------------------------------------------------------ */

function SectionHeading({ data, set, withIntro = true, placeholder = 'Titre de la section' }) {
    return (
        <>
            <h2 className="section-title">
                <InlineText value={data.heading} onChange={(heading) => set({ heading })} placeholder={placeholder} maxLength={120} ai aiLabel="le titre" />
            </h2>
            {withIntro && (
                <p className="section-intro">
                    <InlineText value={data.intro} onChange={(intro) => set({ intro })} placeholder="Introduction (facultative)" multiline maxLength={500} ai aiLabel="l'introduction" />
                </p>
            )}
        </>
    );
}

function SectionLinkPreview({ data, set }) {
    if (!data.link) return null;

    return (
        <p className="section-link">
            <span className="button button-secondary">
                <InlineText value={data.link.label} onChange={(label) => set({ link: { ...data.link, label } })} maxLength={60} />
            </span>
        </p>
    );
}

function LinkSetting({ data, set }) {
    const { pages, currentKey } = useWorkspaceContext();
    const options = pages.filter((page) => page.key !== currentKey).map((page) => ({ value: page.key, label: page.nav_label }));

    return (
        <div>
            <SettingLabel>Bouton vers une autre page</SettingLabel>
            <Select
                size="xs"
                placeholder="Aucun bouton"
                clearable
                data={options}
                value={data.link?.page ?? null}
                onChange={(page) => set({ link: page ? { page, label: data.link?.label ?? 'En savoir plus' } : null })}
                comboboxProps={{ withinPortal: true }}
            />
        </div>
    );
}

function ParagraphsField({ paragraphs, onChange }) {
    return (
        <InlineText
            value={(paragraphs ?? []).join('\n\n')}
            onChange={(text) => onChange(text.split(/\n{2,}/))}
            placeholder="Texte (séparez les paragraphes par une ligne vide)"
            multiline
            maxLength={8000}
            ai
            aiLabel="le texte"
        />
    );
}

/* ------------------------------------------------------------------ */
/* En-têtes de page                                                    */
/* ------------------------------------------------------------------ */

function HeadTypeSetting({ props, data }) {
    const { editor, getPos, node } = props;
    const isHero = data.type === 'hero';

    const switchType = () => {
        const next = isHero
            ? { type: 'page_header', h1: data.h1, lead: data.lead }
            : { type: 'hero', h1: data.h1, lead: data.lead, image: null, variant: 'plain', cta_label: null };
        const pos = getPos();

        editor.chain().insertContentAt({ from: pos, to: pos + node.nodeSize }, { type: nodeName(next.type), attrs: { data: next } }).run();
    };

    return (
        <Button size="xs" variant="light" leftSection={<IconArrowsExchange size={14} />} onClick={switchType}>
            {isHero ? 'Remplacer par un en-tête simple' : 'Remplacer par un bandeau d\'accueil'}
        </Button>
    );
}

export function HeroView(props) {
    const [data, set] = useSection(props);
    const { site, mediaById } = useWorkspaceContext();
    const hasImage = Boolean(data.image && mediaById[data.image]);
    const variant = hasImage ? data.variant ?? 'split' : 'plain';
    const image = hasImage ? mediaById[data.image] : null;

    const settings = (
        <>
            <div>
                <SettingLabel>Mise en page</SettingLabel>
                <Select
                    size="xs"
                    allowDeselect={false}
                    value={data.variant ?? 'split'}
                    onChange={(value) => set({ variant: value })}
                    data={Object.entries(LAYOUT_LABELS).map(([value, label]) => ({ value, label }))}
                    comboboxProps={{ withinPortal: true }}
                />
                {!hasImage && <Text size="xs" c="dimmed" mt={4}>Sans photo, le bandeau s'affiche en texte seul.</Text>}
            </div>
            <div>
                <SettingLabel>Photo du bandeau</SettingLabel>
                <MediaSlot mediaId={data.image} onChange={(id) => set({ image: id })} className="ed-media--setting" compact />
            </div>
            <HeadTypeSetting props={props} data={data} />
        </>
    );

    return (
        <SectionFrame props={props} label="Bandeau d'accueil" settings={settings}>
            <section className={`hero hero--${variant.replace('_', '-')}${variant === 'boxed' ? ' hero--image' : ''}`}>
                {BACKGROUND_LAYOUTS.includes(variant) && image?.large && <div className="hero-bg"><img src={image.large} alt="" /></div>}
                <div className="container hero-inner">
                    <div className="hero-text">
                        <h1><InlineText value={data.h1} onChange={(h1) => set({ h1 })} placeholder="Titre principal de la page (H1)" maxLength={90} ai aiLabel="le titre" /></h1>
                        <p className="lead"><InlineText value={data.lead} onChange={(lead) => set({ lead })} placeholder="Accroche : une à deux phrases" multiline maxLength={300} ai aiLabel="l'accroche" /></p>
                        <div className="actions">
                            {site.phone && <span className="button button-primary">Appeler le {site.phone}</span>}
                            <span className="button button-secondary">
                                <InlineText value={data.cta_label ?? 'Demander un contact'} onChange={(cta_label) => set({ cta_label })} maxLength={40} />
                            </span>
                        </div>
                    </div>
                    {MEDIA_LAYOUTS.includes(variant) && (
                        <div className="hero-media">
                            <MediaSlot mediaId={data.image} onChange={(id) => set({ image: id })} />
                        </div>
                    )}
                </div>
                {!hasImage && (
                    <div className="container ed-hero-add-photo">
                        <MediaSlot mediaId={null} onChange={(id) => set({ image: id, variant: data.variant === 'plain' ? 'split' : data.variant ?? 'split' })} label="Ajouter une photo au bandeau" compact />
                    </div>
                )}
            </section>
        </SectionFrame>
    );
}

export function PageHeaderView(props) {
    const [data, set] = useSection(props);

    return (
        <SectionFrame props={props} label="En-tête de page" settings={<HeadTypeSetting props={props} data={data} />}>
            <section className="page-header">
                <div className="container">
                    <h1><InlineText value={data.h1} onChange={(h1) => set({ h1 })} placeholder="Titre principal de la page (H1)" maxLength={90} ai aiLabel="le titre" /></h1>
                    <p className="lead"><InlineText value={data.lead} onChange={(lead) => set({ lead })} placeholder="Accroche (facultative)" multiline maxLength={300} ai aiLabel="l'accroche" /></p>
                </div>
            </section>
        </SectionFrame>
    );
}

/* ------------------------------------------------------------------ */
/* Sections                                                            */
/* ------------------------------------------------------------------ */

export function ServicesView(props) {
    const [data, set] = useSection(props);
    const items = itemsHelpers(data.items, (next) => set({ items: next }));
    const layout = data.layout ?? 'cards';
    const newItem = { name: 'Nouveau service', text: '', image: null };

    const settings = (
        <>
            <div>
                <SettingLabel>Présentation</SettingLabel>
                <SegmentedControl size="xs" fullWidth value={layout} onChange={(value) => set({ layout: value })} data={[{ value: 'cards', label: 'Cartes' }, { value: 'detailed', label: 'Détaillée' }]} />
            </div>
            <LinkSetting data={data} set={set} />
        </>
    );

    return (
        <SectionFrame props={props} label="Services" settings={settings}>
            <section className="section section-services">
                <div className="container">
                    <SectionHeading data={data} set={set} placeholder="Titre de la section (facultatif)" />
                    {layout === 'cards' ? (
                        <ul className="cards">
                            {items.list.map((item, index) => (
                                <li className="card ed-item" key={index}>
                                    <ItemTools index={index} count={items.list.length} onMove={items.move} onRemove={items.remove} />
                                    <MediaSlot mediaId={item.image} onChange={(image) => items.update(index, { image })} className="card-media" label="Photo" compact />
                                    <h3><InlineText value={item.name} onChange={(name) => items.update(index, { name })} placeholder="Nom du service" maxLength={120} /></h3>
                                    <p><InlineText value={item.text} onChange={(text) => items.update(index, { text })} placeholder="Description courte" multiline maxLength={2000} ai aiLabel="la description" /></p>
                                </li>
                            ))}
                            <li className="ed-add-card">
                                <Button variant="light" leftSection={<IconPlus size={16} />} onClick={() => items.add(newItem)}>Ajouter un service</Button>
                            </li>
                        </ul>
                    ) : (
                        <div className="service-list">
                            {items.list.map((item, index) => (
                                <article className="service ed-item" key={index}>
                                    <ItemTools index={index} count={items.list.length} onMove={items.move} onRemove={items.remove} vertical />
                                    <MediaSlot mediaId={item.image} onChange={(image) => items.update(index, { image })} className="service-media" label="Photo" compact />
                                    <div>
                                        <h3 className="service-title"><InlineText value={item.name} onChange={(name) => items.update(index, { name })} placeholder="Nom du service" maxLength={120} /></h3>
                                        <p><InlineText value={item.text} onChange={(text) => items.update(index, { text })} placeholder="Description détaillée" multiline maxLength={2000} ai aiLabel="la description" /></p>
                                    </div>
                                </article>
                            ))}
                            <div><Button variant="light" leftSection={<IconPlus size={16} />} onClick={() => items.add(newItem)}>Ajouter un service</Button></div>
                        </div>
                    )}
                    <SectionLinkPreview data={data} set={set} />
                </div>
            </section>
        </SectionFrame>
    );
}

export function AboutView(props) {
    const [data, set] = useSection(props);
    const { mediaById } = useWorkspaceContext();
    const hasImage = Boolean(data.image && mediaById[data.image]);

    return (
        <SectionFrame props={props} label="Présentation" settings={<LinkSetting data={data} set={set} />}>
            <section className="section section-about">
                <div className={`container ${hasImage ? 'split' : 'narrow'}`}>
                    <div className="prose">
                        <SectionHeading data={data} set={set} withIntro={false} />
                        <p><ParagraphsField paragraphs={data.paragraphs} onChange={(paragraphs) => set({ paragraphs })} /></p>
                        <SectionLinkPreview data={data} set={set} />
                    </div>
                    {hasImage ? (
                        <div className="split-media"><MediaSlot mediaId={data.image} onChange={(image) => set({ image })} /></div>
                    ) : (
                        <MediaSlot mediaId={null} onChange={(image) => set({ image })} label="Ajouter une photo" compact />
                    )}
                </div>
            </section>
        </SectionFrame>
    );
}

export function HighlightsView(props) {
    const [data, set] = useSection(props);
    const items = itemsHelpers(data.items, (next) => set({ items: next }));

    return (
        <SectionFrame props={props} label="Points forts">
            <section className="section section-highlights">
                <div className="container">
                    <SectionHeading data={data} set={set} withIntro={false} />
                    <ul className="highlights">
                        {items.list.map((item, index) => (
                            <li className="ed-item" key={index}>
                                <ItemTools index={index} count={items.list.length} onMove={items.move} onRemove={items.remove} />
                                <h3><InlineText value={item.title} onChange={(title) => items.update(index, { title })} placeholder="Point fort" maxLength={120} /></h3>
                                <p><InlineText value={item.text} onChange={(text) => items.update(index, { text })} placeholder="Explication courte" multiline maxLength={500} ai aiLabel="le texte" /></p>
                            </li>
                        ))}
                    </ul>
                    {items.list.length < 12 && (
                        <Button mt="md" variant="light" size="xs" leftSection={<IconPlus size={14} />} onClick={() => items.add({ title: '', text: '' })}>Ajouter un point fort</Button>
                    )}
                </div>
            </section>
        </SectionFrame>
    );
}

export function GalleryView(props) {
    const [data, set] = useSection(props);
    const { mediaById, pickMedia } = useWorkspaceContext();
    const images = (data.images ?? []).filter((id) => mediaById[id]);
    const imageList = itemsHelpers(images, (next) => set({ images: next }));

    const manage = async () => {
        const selected = await pickMedia({ multiple: true, selected: images, excludeAi: true, title: 'Photos de la galerie' });

        if (selected) set({ images: selected });
    };

    return (
        <SectionFrame props={props} label="Galerie" settings={<LinkSetting data={data} set={set} />}>
            <section className="section section-gallery">
                <div className="container">
                    <SectionHeading data={data} set={set} placeholder="Titre de la galerie (facultatif)" />
                    <ul className="gallery">
                        {images.map((id, index) => (
                            <li key={id} className="ed-item">
                                <ItemTools index={index} count={images.length} onMove={imageList.move} onRemove={imageList.remove} />
                                <span className="ed-gallery-thumb"><img src={mediaById[id].large ?? mediaById[id].thumb} alt={mediaById[id].alt ?? ''} /></span>
                                {mediaById[id].caption && <p className="gallery-caption">{mediaById[id].caption}</p>}
                            </li>
                        ))}
                    </ul>
                    <Button mt="md" variant="light" leftSection={<IconPhotoPlus size={16} />} onClick={manage}>
                        {images.length ? 'Choisir les photos' : 'Ajouter des photos de réalisations'}
                    </Button>
                    <SectionLinkPreview data={data} set={set} />
                </div>
            </section>
        </SectionFrame>
    );
}

export function ZoneView(props) {
    const [data, set] = useSection(props);
    const [town, setTown] = useState('');
    const towns = data.towns ?? [];

    const addTown = () => {
        const value = town.trim();

        if (value && !towns.includes(value)) set({ towns: [...towns, value] });

        setTown('');
    };

    return (
        <SectionFrame props={props} label="Zone d'intervention">
            <section className="section">
                <div className="container narrow">
                    <SectionHeading data={data} set={set} withIntro={false} />
                    <p><InlineText value={data.text} onChange={(text) => set({ text })} placeholder="Où intervenez-vous ?" multiline maxLength={1000} ai aiLabel="le texte" /></p>
                    <ul className="chips">
                        {towns.map((name) => (
                            <li key={name} className="ed-chip">
                                {name}
                                <ActionIcon size="xs" variant="transparent" color="gray" onClick={() => set({ towns: towns.filter((candidate) => candidate !== name) })} aria-label={`Retirer ${name}`}>
                                    <IconX size={12} />
                                </ActionIcon>
                            </li>
                        ))}
                        <li className="ed-chip ed-chip--input">
                            <input
                                value={town}
                                placeholder="+ Commune"
                                maxLength={60}
                                onChange={(event) => setTown(event.target.value)}
                                onBlur={addTown}
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter' || event.key === ',') {
                                        event.preventDefault();
                                        addTown();
                                    }
                                }}
                            />
                        </li>
                    </ul>
                </div>
            </section>
        </SectionFrame>
    );
}

export function FaqView(props) {
    const [data, set] = useSection(props);
    const items = itemsHelpers(data.items, (next) => set({ items: next }));

    return (
        <SectionFrame props={props} label="Questions fréquentes">
            <section className="section">
                <div className="container narrow">
                    <SectionHeading data={data} set={set} withIntro={false} />
                    <div className="faq">
                        {items.list.map((item, index) => (
                            <div className="ed-faq-item ed-item" key={index}>
                                <ItemTools index={index} count={items.list.length} onMove={items.move} onRemove={items.remove} vertical />
                                <div className="ed-faq-question"><InlineText value={item.question} onChange={(question) => items.update(index, { question })} placeholder="Question" maxLength={200} /></div>
                                <p><InlineText value={item.answer} onChange={(answer) => items.update(index, { answer })} placeholder="Réponse" multiline maxLength={1500} ai aiLabel="la réponse" /></p>
                            </div>
                        ))}
                    </div>
                    <Button mt="md" variant="light" size="xs" leftSection={<IconPlus size={14} />} onClick={() => items.add({ question: '', answer: '' })}>Ajouter une question</Button>
                </div>
            </section>
        </SectionFrame>
    );
}

export function CtaView(props) {
    const [data, set] = useSection(props);
    const { site } = useWorkspaceContext();

    return (
        <SectionFrame props={props} label="Appel à l'action">
            <section className="cta-band">
                <div className="container cta-inner">
                    <div>
                        <h2><InlineText value={data.heading} onChange={(heading) => set({ heading })} placeholder="Titre de l'appel à l'action" maxLength={120} ai aiLabel="le titre" /></h2>
                        <p><InlineText value={data.text} onChange={(text) => set({ text })} placeholder="Texte (facultatif)" multiline maxLength={300} ai aiLabel="le texte" /></p>
                    </div>
                    <div className="actions">
                        {site.phone && <span className="button button-light">{site.phone}</span>}
                        <span className="button button-outline-light">Écrire un message</span>
                    </div>
                </div>
            </section>
        </SectionFrame>
    );
}

export function ContactView(props) {
    const [data, set] = useSection(props);
    const { site } = useWorkspaceContext();

    const settings = (
        <Switch
            label="Proposer la carte Google Maps"
            description="Chargée seulement si le visiteur clique (vie privée et performance)."
            checked={Boolean(data.show_map)}
            onChange={(event) => set({ show_map: event.currentTarget.checked })}
        />
    );

    return (
        <SectionFrame props={props} label="Contact" settings={settings}>
            <section className="section">
                <div className="container contact-grid">
                    <div>
                        <SectionHeading data={data} set={set} withIntro={false} />
                        <p className="section-intro"><InlineText value={data.text} onChange={(text) => set({ text })} placeholder="Texte au-dessus du formulaire" multiline maxLength={500} ai aiLabel="le texte" /></p>
                        <div className="contact-form ed-static">
                            <div className="field"><label>Nom</label><input disabled /></div>
                            <div className="field-row">
                                <div className="field"><label>Email</label><input disabled /></div>
                                <div className="field"><label>Téléphone <span className="optional">(facultatif)</span></label><input disabled /></div>
                            </div>
                            <div className="field"><label>Votre message</label><textarea rows={3} disabled /></div>
                            <span className="button button-primary">Envoyer</span>
                        </div>
                    </div>
                    <aside className="contact-card ed-static">
                        <h2 className="contact-card-title">{site.name}</h2>
                        <ul className="contact-list">
                            {site.phone && <li><span>Téléphone</span>{site.phone}</li>}
                        </ul>
                        <Text size="xs" c="dimmed" mt="sm">Coordonnées et horaires repris automatiquement de la fiche du site.</Text>
                    </aside>
                </div>
            </section>
        </SectionFrame>
    );
}

export const SECTION_VIEWS = {
    hero: HeroView,
    page_header: PageHeaderView,
    services: ServicesView,
    about: AboutView,
    highlights: HighlightsView,
    gallery: GalleryView,
    zone: ZoneView,
    faq: FaqView,
    cta: CtaView,
    contact: ContactView,
};

