import '@mantine/core/styles.css';
import '@mantine/notifications/styles.css';
import '@mantine/dropzone/styles.css';
import './editor.css';

import { createTheme, MantineProvider } from '@mantine/core';
import { ModalsProvider } from '@mantine/modals';
import { Notifications } from '@mantine/notifications';
import { createRoot } from 'react-dom/client';
import App from './App';

const theme = createTheme({
    primaryColor: 'indigo',
    defaultRadius: 'md',
    fontFamily: 'Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
    cursorType: 'pointer',
});

const root = document.getElementById('editor-root');

createRoot(root).render(
    <MantineProvider theme={theme} defaultColorScheme="auto">
        <ModalsProvider labels={{ confirm: 'Confirmer', cancel: 'Annuler' }}>
            <Notifications position="bottom-right" limit={4} />
            <App apiUrl={root.dataset.api} />
        </ModalsProvider>
    </MantineProvider>,
);
