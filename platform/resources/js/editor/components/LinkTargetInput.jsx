import { SegmentedControl, Select, Stack, TextInput } from '@mantine/core';
import { useState } from 'react';
import { useWorkspaceContext } from '../lib/context';

const kindOf = (href) => {
    if (!href || href.startsWith('page:')) return 'page';
    if (href.startsWith('tel:')) return 'tel';
    if (href.startsWith('mailto:')) return 'mailto';

    return 'url';
};

/**
 * Choix de la cible d'un lien ou d'un bouton : page du site (lien stable), adresse web, téléphone ou email.
 * Produit un href au format enregistré : « page:clé », « https://… », « tel:… », « mailto:… ».
 */
export default function LinkTargetInput({ value, onChange, withinPortal = true }) {
    const { pages, site } = useWorkspaceContext();
    const [kind, setKind] = useState(kindOf(value));

    const raw = value?.replace(/^(page|tel|mailto):/, '') ?? '';

    const changeKind = (next) => {
        setKind(next);

        if (next === 'page') onChange('page:home');
        if (next === 'tel') onChange(site.phone ? `tel:${site.phone.replace(/[^\d+]/g, '').replace(/^0/, '+33')}` : 'tel:');
        if (next === 'mailto') onChange('mailto:');
        if (next === 'url') onChange('https://');
    };

    return (
        <Stack gap="xs">
            <SegmentedControl
                size="xs"
                fullWidth
                value={kind}
                onChange={changeKind}
                data={[{ value: 'page', label: 'Page' }, { value: 'url', label: 'Web' }, { value: 'tel', label: 'Tél.' }, { value: 'mailto', label: 'Email' }]}
            />
            {kind === 'page' && (
                <Select
                    size="xs"
                    data={pages.map((page) => ({ value: page.key, label: page.nav_label }))}
                    value={raw.split('#')[0] || 'home'}
                    onChange={(key) => onChange(`page:${key ?? 'home'}`)}
                    allowDeselect={false}
                    comboboxProps={{ withinPortal }}
                />
            )}
            {kind === 'url' && <TextInput size="xs" value={value ?? ''} onChange={(event) => onChange(event.currentTarget.value.trim())} placeholder="https://…" />}
            {kind === 'tel' && <TextInput size="xs" value={raw} onChange={(event) => onChange(`tel:${event.currentTarget.value.replace(/[^\d+]/g, '')}`)} placeholder="+33612345678" />}
            {kind === 'mailto' && <TextInput size="xs" value={raw} onChange={(event) => onChange(`mailto:${event.currentTarget.value.trim()}`)} placeholder="contact@exemple.fr" />}
        </Stack>
    );
}
