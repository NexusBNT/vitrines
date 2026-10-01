import { ActionIcon, Badge, Burger, Group, List, Popover, Text, Tooltip, useComputedColorScheme, useMantineColorScheme } from '@mantine/core';
import { IconAlertTriangle, IconCloudCheck, IconCloudUp, IconEye, IconHistory, IconLoader2, IconMoon, IconSettings, IconSun, IconWifiOff } from '@tabler/icons-react';

const STATUS = {
    saved: { label: 'Enregistré', icon: IconCloudCheck, color: 'teal' },
    dirty: { label: 'Modifications en attente', icon: IconCloudUp, color: 'gray' },
    saving: { label: 'Enregistrement…', icon: IconLoader2, color: 'gray', spin: true },
    error: { label: 'Non enregistré', icon: IconWifiOff, color: 'red' },
    conflict: { label: 'Conflit de version', icon: IconAlertTriangle, color: 'red' },
};

export default function TopBar({ page, parent, path, status, error, warnings, suggestions, panel, onPanel, sidebarOpen, onToggleSidebar }) {
    const { setColorScheme } = useMantineColorScheme();
    const scheme = useComputedColorScheme('light');
    const current = STATUS[status];
    const notes = [...warnings, ...suggestions.map((suggestion) => `Suggestion de l'IA : ${suggestion}`)];

    const panelButton = (id, label, Icon) => (
        <Tooltip label={label}>
            <ActionIcon variant={panel === id ? 'light' : 'subtle'} color={panel === id ? 'indigo' : 'gray'} size="lg" onClick={() => onPanel(panel === id ? null : id)} aria-label={label} aria-pressed={panel === id}>
                <Icon size={19} stroke={1.7} />
            </ActionIcon>
        </Tooltip>
    );

    return (
        <header className="ed-topbar">
            <Group gap="sm" wrap="nowrap" style={{ minWidth: 0 }}>
                <Burger opened={sidebarOpen} onClick={onToggleSidebar} size="sm" hiddenFrom="md" aria-label="Pages" />
                <div className="ed-crumbs">
                    {parent && <><span className="ed-crumb-parent">{parent.nav_label}</span><span className="ed-crumb-sep">/</span></>}
                    <span className="ed-crumb-current">{page.key === 'home' ? 'Accueil' : page.nav_label}</span>
                    <Badge variant="light" color="gray" size="sm" radius="sm" className="ed-crumb-path" tt="none">{path}</Badge>
                </div>
            </Group>

            <Group gap={6} wrap="nowrap">
                <Tooltip label={error ?? current.label} multiline maw={320}>
                    <Group gap={6} className="ed-status" c={current.color} wrap="nowrap">
                        <current.icon size={17} className={current.spin ? 'ed-spin' : undefined} />
                        <Text size="xs" visibleFrom="sm">{current.label}</Text>
                    </Group>
                </Tooltip>

                {notes.length > 0 && (
                    <Popover width={380} position="bottom-end" shadow="lg">
                        <Popover.Target>
                            <Tooltip label="Points à vérifier">
                                <ActionIcon variant="light" color="orange" size="lg" aria-label="Points à vérifier">
                                    <IconAlertTriangle size={18} />
                                    <span className="ed-dot">{notes.length}</span>
                                </ActionIcon>
                            </Tooltip>
                        </Popover.Target>
                        <Popover.Dropdown>
                            <Text size="sm" fw={600} mb="xs">Points à vérifier</Text>
                            <List size="sm" spacing={6}>
                                {notes.map((note, index) => <List.Item key={index}>{note}</List.Item>)}
                            </List>
                        </Popover.Dropdown>
                    </Popover>
                )}

                <span className="ed-topbar-sep" />
                {panelButton('settings', 'Réglages et référencement de la page', IconSettings)}
                {panelButton('versions', 'Historique des versions', IconHistory)}
                {panelButton('preview', 'Aperçu du site', IconEye)}
                <Tooltip label={scheme === 'dark' ? 'Interface claire' : 'Interface sombre'}>
                    <ActionIcon variant="subtle" color="gray" size="lg" onClick={() => setColorScheme(scheme === 'dark' ? 'light' : 'dark')} aria-label="Changer de thème">
                        {scheme === 'dark' ? <IconSun size={18} /> : <IconMoon size={18} />}
                    </ActionIcon>
                </Tooltip>
            </Group>
        </header>
    );
}
