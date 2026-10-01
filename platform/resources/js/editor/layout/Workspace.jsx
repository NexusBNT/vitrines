import { Button, Group, Modal, Text } from '@mantine/core';
import { modals } from '@mantine/modals';
import { notifications } from '@mantine/notifications';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import PageCanvas from '../canvas/PageCanvas';
import { insertTopLevel } from '../canvas/commands';
import AiWriteModal from '../components/AiWriteModal';
import MediaPicker from '../components/MediaPicker';
import NewPageModal, { templateSections } from '../components/NewPageModal';
import { WorkspaceContext } from '../lib/context';
import { newPageKey, pagePath, slugify } from '../lib/convert';
import { insertPage, movePage, removePage } from '../lib/tree';
import { useAutosave } from '../lib/useAutosave';
import PageSettings from '../panels/PageSettings';
import PreviewPanel from '../panels/PreviewPanel';
import VersionsPanel from '../panels/VersionsPanel';
import Sidebar from './Sidebar';
import TopBar from './TopBar';

const PREVIEW_DELAY = 2500;

const notifyError = (title, message) => notifications.show({ color: 'red', title, message, autoClose: 8000 });

/**
 * Espace de travail : arborescence des pages, canevas de la page ouverte, panneaux (réglages, aperçu, historique).
 */
export default function Workspace({ api, boot }) {
    const [conflict, setConflict] = useState(null);
    const [panel, setPanel] = useState(null);
    const [preview, setPreview] = useState({ url: boot.urls.preview, issues: null, building: false, builtAt: 0, error: null });
    const [media, setMedia] = useState(boot.media);
    const [pickerRequest, setPickerRequest] = useState(null);
    const [aiWrite, setAiWrite] = useState(null);
    const [newPage, setNewPage] = useState(null);
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [canvasVersion, setCanvasVersion] = useState(0);
    const [suggestions] = useState(boot.content.suggestions ?? []);
    const panelRef = useRef(panel);
    const previewTimer = useRef(null);
    panelRef.current = panel;

    const buildPreviewRef = useRef(null);
    const { pages, setPages, status, error, warnings, flush, reset } = useAutosave(api, boot.content, {
        onSaved: () => {
            if (panelRef.current !== 'preview') return;

            clearTimeout(previewTimer.current);
            previewTimer.current = setTimeout(() => buildPreviewRef.current?.(), PREVIEW_DELAY);
        },
        onConflict: setConflict,
    });

    const [currentKey, setCurrentKey] = useState(() => {
        const fromHash = decodeURIComponent(window.location.hash.slice(1));

        return boot.content.pages.some((page) => page.key === fromHash) ? fromHash : 'home';
    });
    const page = pages.find((candidate) => candidate.key === currentKey) ?? pages[0];
    const parent = page.parent ? pages.find((candidate) => candidate.key === page.parent) : null;
    const path = pagePath(page, pages);

    useEffect(() => {
        window.history.replaceState(null, '', `#${page.key}`);
        document.title = `${page.nav_label} · ${boot.site.name}`;
    }, [page.key, page.nav_label, boot.site.name]);

    /* Pages */

    const updatePage = useCallback((key, patch) => {
        setPages((current) => current.map((candidate) => (candidate.key === key ? { ...candidate, ...patch } : candidate)));
    }, [setPages]);

    const handleSections = useCallback((sections) => updatePage(page.key, { sections }), [page.key, updatePage]);

    const selectPage = useCallback((key) => {
        setCurrentKey(key);
        setSidebarOpen(false);
    }, []);

    const createPage = ({ label, parent: parentKey, template, title }) => {
        const key = newPageKey();

        setPages((current) => insertPage(current, {
            key,
            segment: slugify(label),
            parent: parentKey,
            nav_label: label,
            in_nav: true,
            title,
            meta_description: '',
            noindex: false,
            sections: templateSections(template, label),
        }));
        setNewPage(null);
        setCurrentKey(key);
    };

    const deletePage = useCallback((key) => {
        const target = pages.find((candidate) => candidate.key === key);
        const children = pages.filter((candidate) => candidate.parent === key);

        modals.openConfirmModal({
            title: `Supprimer la page « ${target.nav_label} » ?`,
            children: (
                <Text size="sm">
                    Son contenu restera récupérable dans l'historique des versions.
                    {children.length > 0 && ` Ses ${children.length} sous-page(s) remonteront au premier niveau du menu.`}
                </Text>
            ),
            labels: { confirm: 'Supprimer', cancel: 'Annuler' },
            confirmProps: { color: 'red' },
            onConfirm: () => {
                setPages((current) => removePage(current, key));
                setCurrentKey((current) => (current === key ? 'home' : current));
            },
        });
    }, [pages, setPages]);

    const pageLinksSignature = pages.map((candidate) => `${candidate.key}:${candidate.nav_label}`).join('|');
    const pageLinks = useMemo(
        () => pages.map((candidate) => ({ key: candidate.key, nav_label: candidate.nav_label })),
        // Ne change que si une page est ajoutée, supprimée ou renommée (pas à chaque frappe).
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [pageLinksSignature],
    );

    /* Photos */

    const mediaById = useMemo(() => Object.fromEntries(media.map((item) => [item.id, item])), [media]);
    const hasPendingMedia = media.some((item) => !item.ready);

    useEffect(() => {
        if (!hasPendingMedia) return undefined;

        const timer = setInterval(() => api.media().then((data) => setMedia(data.media)).catch(() => {}), 3000);

        return () => clearInterval(timer);
    }, [api, hasPendingMedia]);

    const uploadMedia = useCallback(async (file) => {
        try {
            const { media: item } = await api.upload(file);
            setMedia((current) => (current.some((candidate) => candidate.id === item.id) ? current : [...current, item]));

            return item;
        } catch (exception) {
            notifyError(`« ${file.name} » n'a pas été ajoutée`, exception.message);

            return null;
        }
    }, [api]);

    const pickMedia = useCallback((options = {}) => new Promise((resolve) => setPickerRequest({ ...options, resolve })), []);

    /* IA */

    const openAiWrite = useCallback((editor, preset = null) => setAiWrite({ editor, preset }), []);

    const insertAiBlocks = (blocks) => {
        insertTopLevel(aiWrite.editor, blocks);
        setAiWrite(null);
    };

    /* Aperçu */

    const buildPreview = useCallback(async () => {
        await flush();
        setPreview((current) => ({ ...current, building: true, error: null }));

        try {
            const result = await api.preview();
            setPreview({ url: result.url, issues: result.issues, building: false, builtAt: Date.now(), error: null });
        } catch (exception) {
            setPreview((current) => ({ ...current, building: false, error: exception.message }));
        }
    }, [api, flush]);
    buildPreviewRef.current = buildPreview;

    const openPanel = (next) => {
        setPanel(next);

        if (next === 'preview') buildPreview();
    };

    /* Historique */

    const onRestored = useCallback((content) => {
        reset(content);
        setCanvasVersion((value) => value + 1);
        setCurrentKey((current) => (content.pages.some((candidate) => candidate.key === current) ? current : 'home'));
        notifications.show({ color: 'teal', title: 'Version restaurée', message: 'Le contenu des pages a été remplacé.' });
    }, [reset]);

    /* Raccourcis */

    useEffect(() => {
        const onKeyDown = (event) => {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's') {
                event.preventDefault();
                flush();
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [flush]);

    const context = useMemo(() => ({
        api,
        site: boot.site,
        sectionLabels: boot.sections,
        reservedSlugs: boot.reserved_slugs,
        aiAvailable: boot.ai.available,
        pages: pageLinks,
        currentKey: page.key,
        media,
        mediaById,
        pickMedia,
        uploadMedia,
        openAiWrite,
        notifyError,
    }), [api, boot, pageLinks, page.key, media, mediaById, pickMedia, uploadMedia, openAiWrite]);

    return (
        <WorkspaceContext.Provider value={context}>
            <div className={`ed-shell${sidebarOpen ? ' is-sidebar-open' : ''}`}>
                <Sidebar
                    site={boot.site}
                    urls={boot.urls}
                    pages={pages}
                    currentKey={page.key}
                    protectedPages={boot.protected_pages}
                    onSelect={selectPage}
                    onMove={(key, parentKey, index) => setPages((current) => movePage(current, key, parentKey, index))}
                    onRename={(key, name) => name.trim() && updatePage(key, { nav_label: name.trim().slice(0, 30) })}
                    onAddPage={(parentKey) => setNewPage({ parent: parentKey })}
                    onDeletePage={deletePage}
                    onToggleNav={(key) => updatePage(key, { in_nav: pages.find((candidate) => candidate.key === key)?.in_nav === false })}
                    onOpenSettings={() => setPanel('settings')}
                    onOpenMedia={() => pickMedia({ browse: true, title: 'Photos du site' })}
                />
                <div className="ed-scrim" onClick={() => setSidebarOpen(false)} />

                <div className="ed-main">
                    <TopBar
                        page={page}
                        parent={parent}
                        path={path}
                        status={status}
                        error={error}
                        warnings={warnings}
                        suggestions={suggestions}
                        panel={panel}
                        onPanel={openPanel}
                        sidebarOpen={sidebarOpen}
                        onToggleSidebar={() => setSidebarOpen((value) => !value)}
                    />
                    {status === 'error' && error && <div className="ed-error-bar">{error}</div>}
                    <div className="ed-body">
                        <div className="ed-canvas-scroll">
                            <div className={`site-canvas ${boot.theme.body_classes}`}>
                                <PageCanvas key={`${page.key}-${canvasVersion}`} page={page} onChange={handleSections} />
                            </div>
                        </div>
                        {panel === 'settings' && (
                            <PageSettings page={page} pages={pages} onChange={(patch) => updatePage(page.key, patch)} onClose={() => setPanel(null)} onFlush={flush} />
                        )}
                        {panel === 'preview' && <PreviewPanel preview={preview} path={path} onRefresh={buildPreview} onClose={() => setPanel(null)} />}
                        {panel === 'versions' && <VersionsPanel onClose={() => setPanel(null)} onRestored={onRestored} onFlush={flush} />}
                    </div>
                </div>
            </div>

            {pickerRequest && (
                <MediaPicker
                    request={pickerRequest}
                    media={media}
                    onUpload={uploadMedia}
                    onResolve={(value) => {
                        pickerRequest.resolve(value);
                        setPickerRequest(null);
                    }}
                />
            )}
            {aiWrite && <AiWriteModal request={aiWrite} api={api} pageKey={page.key} onInsert={insertAiBlocks} onClose={() => setAiWrite(null)} />}
            {newPage && <NewPageModal pages={pages} parent={newPage.parent} siteName={boot.site.name} onCreate={createPage} onClose={() => setNewPage(null)} />}

            <Modal opened={Boolean(conflict)} onClose={() => {}} withCloseButton={false} centered title="Contenu modifié ailleurs">
                <Text size="sm">{conflict}</Text>
                <Text size="sm" c="dimmed" mt="xs">Vos dernières modifications de cet onglet ne sont pas enregistrées.</Text>
                <Group justify="flex-end" mt="md">
                    <Button onClick={() => window.location.reload()}>Recharger</Button>
                </Group>
            </Modal>
        </WorkspaceContext.Provider>
    );
}
