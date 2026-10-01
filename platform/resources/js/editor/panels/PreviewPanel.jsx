import { ActionIcon, Badge, Collapse, Group, Loader, SegmentedControl, Stack, Text, Tooltip, UnstyledButton } from '@mantine/core';
import { IconAlertTriangle, IconDeviceDesktop, IconDeviceMobile, IconExternalLink, IconEye, IconRefresh } from '@tabler/icons-react';
import { useElementSize } from '@mantine/hooks';
import { useState } from 'react';

const DEVICES = { desktop: 1280, mobile: 390 };
import PanelHeader from './PanelHeader';

/**
 * Aperçu réel du site (build de prévisualisation), sur ordinateur ou mobile, avec le contrôle qualité.
 */
export default function PreviewPanel({ preview, path, onRefresh, onClose }) {
    const [device, setDevice] = useState('desktop');
    const [showIssues, setShowIssues] = useState(false);
    const errors = preview.issues?.filter((issue) => issue.level === 'error') ?? [];
    const warnings = preview.issues?.filter((issue) => issue.level !== 'error') ?? [];
    const src = preview.url && preview.builtAt ? `${preview.url}${path}?t=${preview.builtAt}` : null;
    const { ref: stageRef, width: stageWidth, height: stageHeight } = useElementSize();
    const frameWidth = DEVICES[device];
    const scale = stageWidth ? Math.min(1, (stageWidth - 24) / frameWidth) : 1;

    return (
        <aside className={`ed-panel ed-panel--preview is-${device}`}>
            <PanelHeader title="Aperçu" icon={IconEye} onClose={onClose}>
                <SegmentedControl
                    size="xs"
                    value={device}
                    onChange={setDevice}
                    data={[
                        { value: 'desktop', label: <IconDeviceDesktop size={16} aria-label="Ordinateur" /> },
                        { value: 'mobile', label: <IconDeviceMobile size={16} aria-label="Mobile" /> },
                    ]}
                />
                <Tooltip label="Actualiser l'aperçu">
                    <ActionIcon variant="subtle" color="gray" onClick={onRefresh} loading={preview.building} aria-label="Actualiser"><IconRefresh size={18} /></ActionIcon>
                </Tooltip>
                {src && (
                    <Tooltip label="Ouvrir dans un onglet">
                        <ActionIcon variant="subtle" color="gray" component="a" href={src} target="_blank" aria-label="Ouvrir dans un onglet"><IconExternalLink size={18} /></ActionIcon>
                    </Tooltip>
                )}
            </PanelHeader>

            {preview.issues && (errors.length > 0 || warnings.length > 0) && (
                <div className="ed-preview-issues">
                    <UnstyledButton onClick={() => setShowIssues((value) => !value)} w="100%">
                        <Group gap={8}>
                            <IconAlertTriangle size={16} color={errors.length ? 'var(--mantine-color-red-6)' : 'var(--mantine-color-orange-6)'} />
                            <Text size="sm">Contrôle qualité</Text>
                            {errors.length > 0 && <Badge size="xs" color="red">{errors.length} erreur(s)</Badge>}
                            {warnings.length > 0 && <Badge size="xs" color="orange">{warnings.length} avertissement(s)</Badge>}
                        </Group>
                    </UnstyledButton>
                    <Collapse in={showIssues}>
                        <Stack gap={4} mt="xs">
                            {[...errors, ...warnings].map((issue, index) => (
                                <Text key={index} size="xs" c={issue.level === 'error' ? 'red' : 'dimmed'}>{issue.page} — {issue.message}</Text>
                            ))}
                        </Stack>
                    </Collapse>
                </div>
            )}

            <div className="ed-preview-stage" ref={stageRef}>
                {preview.building && !src && <Group justify="center" p="xl"><Loader type="dots" /></Group>}
                {preview.error && <Text c="red" size="sm" p="md">{preview.error}</Text>}
                {src && (
                    <div className="ed-preview-frame" style={{ width: frameWidth * scale, height: Math.max(0, stageHeight - 24) }}>
                        <iframe
                            key={src}
                            src={src}
                            title="Aperçu du site"
                            style={{ width: frameWidth, height: Math.max(0, stageHeight - 24) / scale, transform: `scale(${scale})` }}
                        />
                        {preview.building && <div className="ed-preview-busy"><Loader size="sm" /></div>}
                    </div>
                )}
            </div>
        </aside>
    );
}
