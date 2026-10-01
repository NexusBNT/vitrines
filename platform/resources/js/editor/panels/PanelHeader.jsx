import { ActionIcon, Group, Text } from '@mantine/core';
import { IconX } from '@tabler/icons-react';

export default function PanelHeader({ title, icon: Icon, onClose, children }) {
    return (
        <Group className="ed-panel-head" justify="space-between" wrap="nowrap">
            <Group gap={8} wrap="nowrap">
                {Icon && <Icon size={18} stroke={1.6} />}
                <Text fw={600}>{title}</Text>
            </Group>
            <Group gap={4} wrap="nowrap">
                {children}
                <ActionIcon variant="subtle" color="gray" onClick={onClose} aria-label="Fermer le panneau"><IconX size={18} /></ActionIcon>
            </Group>
        </Group>
    );
}
