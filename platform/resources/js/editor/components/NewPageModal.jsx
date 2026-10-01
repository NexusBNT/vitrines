import { Button, Group, Modal, Radio, Select, Stack, Text, TextInput } from '@mantine/core';
import { useState } from 'react';
import { pagePath, slugify } from '../lib/convert';

export const PAGE_TEMPLATES = [
    { id: 'blank', label: 'Page vide', description: 'Un titre, puis vos blocs.' },
    { id: 'service', label: 'Page de service', description: 'Présentation détaillée, points forts, appel à l\'action.' },
    { id: 'faq', label: 'Questions fréquentes', description: 'Liste de questions-réponses et appel à l\'action.' },
];

export function templateSections(template, title) {
    const head = { type: 'page_header', h1: title, lead: null };

    switch (template) {
        case 'service':
            return [head, { type: 'content', blocks: [{ type: 'paragraph' }] }, { type: 'highlights', heading: 'Pourquoi nous choisir', items: [{ title: '', text: '' }, { title: '', text: '' }, { title: '', text: '' }] }, { type: 'cta', heading: 'Un projet ? Parlons-en', text: '' }];
        case 'faq':
            return [head, { type: 'faq', heading: null, items: [{ question: '', answer: '' }] }, { type: 'cta', heading: 'Vous ne trouvez pas votre réponse ?', text: '' }];
        default:
            return [head];
    }
}

/**
 * Création d'une page : nom, emplacement dans l'arborescence et modèle de départ.
 */
export default function NewPageModal({ pages, parent: initialParent, siteName, onCreate, onClose }) {
    const [label, setLabel] = useState('');
    const [parent, setParent] = useState(initialParent);
    const [template, setTemplate] = useState('blank');
    const parents = pages.filter((page) => !page.parent && page.key !== 'home');
    const preview = pagePath({ key: 'new', nav_label: label, segment: slugify(label), parent }, pages);

    const submit = (event) => {
        event.preventDefault();

        if (!label.trim()) return;

        onCreate({ label: label.trim(), parent, template, title: `${label.trim()} – ${siteName}`.slice(0, 65) });
    };

    return (
        <Modal opened onClose={onClose} title="Nouvelle page" centered>
            <form onSubmit={submit}>
                <Stack gap="md">
                    <TextInput autoFocus label="Nom de la page" placeholder="Ex. : Rénovation de salle de bain" maxLength={30} value={label} onChange={(event) => setLabel(event.currentTarget.value)} description={label ? `Adresse : ${preview}` : ' '} />
                    <Select
                        label="Emplacement"
                        data={[{ value: '', label: 'Premier niveau du menu' }, ...parents.map((page) => ({ value: page.key, label: `Sous « ${page.nav_label} »` }))]}
                        value={parent ?? ''}
                        onChange={(value) => setParent(value || null)}
                        allowDeselect={false}
                    />
                    <Radio.Group label="Modèle de départ" value={template} onChange={setTemplate}>
                        <Stack gap={8} mt={6}>
                            {PAGE_TEMPLATES.map((item) => (
                                <Radio.Card key={item.id} value={item.id} radius="md" p="sm" className="ed-template">
                                    <Group wrap="nowrap" align="flex-start">
                                        <Radio.Indicator />
                                        <div>
                                            <Text size="sm" fw={600}>{item.label}</Text>
                                            <Text size="xs" c="dimmed">{item.description}</Text>
                                        </div>
                                    </Group>
                                </Radio.Card>
                            ))}
                        </Stack>
                    </Radio.Group>
                    <Group justify="flex-end">
                        <Button variant="default" onClick={onClose}>Annuler</Button>
                        <Button type="submit" disabled={!label.trim()}>Créer la page</Button>
                    </Group>
                </Stack>
            </form>
        </Modal>
    );
}
