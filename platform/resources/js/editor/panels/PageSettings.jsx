import { Alert, Button, Divider, Group, Progress, ScrollArea, Stack, Switch, Text, Textarea, TextInput } from '@mantine/core';
import { IconSettings, IconSparkles } from '@tabler/icons-react';
import { useState } from 'react';
import { pagePath, slugify } from '../lib/convert';
import { useWorkspaceContext } from '../lib/context';
import PanelHeader from './PanelHeader';

function LengthMeter({ length, min, max }) {
    const color = length === 0 ? 'gray' : length < min || length > max ? 'orange' : 'teal';

    return (
        <Group gap={8} wrap="nowrap" mt={4}>
            <Progress value={Math.min(100, (length / max) * 100)} color={color} size="xs" style={{ flex: 1 }} />
            <Text size="xs" c={color === 'teal' ? 'teal' : 'dimmed'} w={92} ta="right" style={{ whiteSpace: 'nowrap' }}>{length} / {min}–{max}</Text>
        </Group>
    );
}

/**
 * Réglages d'une page : nom dans le menu, adresse, visibilité, et référencement (title, meta description, aperçu Google).
 */
export default function PageSettings({ page, pages, onChange, onClose, onFlush }) {
    const { api, aiAvailable, reservedSlugs, site, notifyError } = useWorkspaceContext();
    const [loadingSeo, setLoadingSeo] = useState(false);
    const isHome = page.key === 'home';
    const path = pagePath(page, pages);
    const parent = pages.find((candidate) => candidate.key === page.parent);
    const segmentReserved = !page.parent && reservedSlugs.includes(page.segment);
    const duplicate = pages.some((other) => other.key !== page.key && pagePath(other, pages) === path);

    const suggestSeo = async () => {
        setLoadingSeo(true);

        try {
            await onFlush();
            const result = await api.aiSeo(page.key);
            onChange({ title: result.title, meta_description: result.meta_description });
        } catch (exception) {
            notifyError('Proposition impossible', exception.message);
        } finally {
            setLoadingSeo(false);
        }
    };

    return (
        <aside className="ed-panel">
            <PanelHeader title="Réglages de la page" icon={IconSettings} onClose={onClose} />
            <ScrollArea className="ed-panel-body" type="auto">
                <Stack gap="md" p="md">
                    <TextInput
                        label="Nom dans le menu"
                        value={page.nav_label}
                        maxLength={30}
                        disabled={isHome}
                        description={isHome ? 'La page d\'accueil est accessible par le nom du site.' : null}
                        onChange={(event) => onChange({ nav_label: event.currentTarget.value })}
                    />

                    {!isHome && (
                        <>
                            <TextInput
                                label="Adresse de la page"
                                leftSection={<Text size="xs" c="dimmed" pl={8}>/{parent ? `${parent.segment}/` : ''}</Text>}
                                leftSectionWidth={parent ? 20 + parent.segment.length * 7 : 24}
                                value={page.segment}
                                placeholder={slugify(page.nav_label)}
                                onChange={(event) => onChange({ segment: slugify(event.currentTarget.value) })}
                                error={segmentReserved ? 'Cette adresse est réservée.' : duplicate ? 'Une autre page utilise déjà cette adresse.' : null}
                                description="Évitez de la changer une fois le site publié : les liens existants ne fonctionneraient plus."
                            />
                            <Switch
                                label="Afficher dans le menu"
                                checked={page.in_nav !== false}
                                onChange={(event) => onChange({ in_nav: event.currentTarget.checked })}
                            />
                        </>
                    )}

                    <Divider label="Référencement" labelPosition="left" />

                    {aiAvailable && (
                        <Button variant="light" color="violet" leftSection={<IconSparkles size={16} />} loading={loadingSeo} onClick={suggestSeo}>
                            Proposer le titre et la description
                        </Button>
                    )}

                    <div>
                        <TextInput
                            label="Titre pour Google (balise title)"
                            value={page.title}
                            maxLength={70}
                            onChange={(event) => onChange({ title: event.currentTarget.value })}
                        />
                        <LengthMeter length={page.title.length} min={30} max={65} />
                    </div>

                    <div>
                        <Textarea
                            label="Meta description"
                            value={page.meta_description}
                            maxLength={170}
                            autosize
                            minRows={3}
                            onChange={(event) => onChange({ meta_description: event.currentTarget.value })}
                        />
                        <LengthMeter length={page.meta_description.length} min={120} max={155} />
                    </div>

                    <div className="ed-serp">
                        <Text size="xs" c="dimmed" mb={6}>Aperçu dans Google</Text>
                        <div className="ed-serp-site">{site.name}</div>
                        <div className="ed-serp-url">{site.name.toLowerCase().replace(/\s+/g, '')}.fr{path === '/' ? '' : ` › ${path.split('/').filter(Boolean).join(' › ')}`}</div>
                        <div className="ed-serp-title">{page.title || 'Titre de la page'}</div>
                        <div className="ed-serp-description">{page.meta_description || 'La meta description apparaîtra ici.'}</div>
                    </div>

                    {!isHome && (
                        <Switch
                            label="Masquer des moteurs de recherche"
                            description="La page reste accessible par lien, mais Google ne l'indexe pas."
                            checked={Boolean(page.noindex)}
                            onChange={(event) => onChange({ noindex: event.currentTarget.checked })}
                        />
                    )}

                    {site.single_page && (
                        <Alert variant="light" color="gray">
                            Site d'une page : le menu renvoie vers les sections pour lesquelles « Lien dans le menu » est activé (réglages de chaque section).
                        </Alert>
                    )}
                </Stack>
            </ScrollArea>
        </aside>
    );
}
