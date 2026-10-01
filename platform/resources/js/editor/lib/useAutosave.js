import { useCallback, useEffect, useRef, useState } from 'react';

const SAVE_DELAY = 1000;
const RETRY_DELAY = 5000;

/**
 * Pages du site et enregistrement automatique (un envoi à la fois, les changements survenus pendant l'envoi
 * repartent aussitôt après). Un conflit de version arrête l'enregistrement jusqu'au rechargement.
 *
 * status : saved | dirty | saving | error | conflict
 */
export function useAutosave(api, content, { onSaved, onConflict } = {}) {
    const [pages, setPagesState] = useState(content.pages);
    const [status, setStatus] = useState('saved');
    const [error, setError] = useState(null);
    const [warnings, setWarnings] = useState(content.warnings ?? []);

    const pagesRef = useRef(content.pages);
    const versionRef = useRef(content.version);
    const dirtyRef = useRef(false);
    const runningRef = useRef(null);
    const timerRef = useRef(null);
    const blockedRef = useRef(false);
    const callbacksRef = useRef({ onSaved, onConflict });
    callbacksRef.current = { onSaved, onConflict };

    const flush = useCallback(async () => {
        clearTimeout(timerRef.current);

        if (runningRef.current) {
            await runningRef.current;

            return dirtyRef.current ? flush() : undefined;
        }

        if (!dirtyRef.current || blockedRef.current) {
            return undefined;
        }

        const sent = pagesRef.current;
        dirtyRef.current = false;
        setStatus('saving');

        runningRef.current = (async () => {
            try {
                const result = await api.savePages(versionRef.current, sent);
                versionRef.current = result.version;
                setWarnings(result.warnings);
                setError(null);

                // Le serveur calcule les adresses laissées vides : on les reprend sans toucher au contenu en cours.
                const segments = Object.fromEntries(result.pages.map((page) => [page.key, page.segment]));
                const withSegments = pagesRef.current.map((page) => (!page.segment && segments[page.key] ? { ...page, segment: segments[page.key] } : page));

                if (withSegments.some((page, index) => page !== pagesRef.current[index])) {
                    pagesRef.current = withSegments;
                    setPagesState(withSegments);
                }

                setStatus(dirtyRef.current ? 'dirty' : 'saved');
                callbacksRef.current.onSaved?.(result);

                return 'ok';
            } catch (exception) {
                dirtyRef.current = true;

                if (exception.status === 409) {
                    blockedRef.current = true;
                    setStatus('conflict');
                    callbacksRef.current.onConflict?.(exception.message);

                    return 'conflict';
                }

                setError(exception.message);
                setStatus('error');

                if (exception.status === 0 || exception.status >= 500) {
                    timerRef.current = setTimeout(() => flush(), RETRY_DELAY);
                }

                return 'failed';
            }
        })();

        const outcome = await runningRef.current;
        runningRef.current = null;

        // Des changements sont arrivés pendant l'envoi : on les enregistre à leur tour.
        if (outcome === 'ok' && dirtyRef.current) {
            timerRef.current = setTimeout(() => flush(), SAVE_DELAY);
        }

        return undefined;
    }, [api]);

    const setPages = useCallback((updater) => {
        const next = typeof updater === 'function' ? updater(pagesRef.current) : updater;

        if (next === pagesRef.current) return;

        pagesRef.current = next;
        dirtyRef.current = true;
        setPagesState(next);
        setStatus((current) => (current === 'conflict' ? current : 'dirty'));

        clearTimeout(timerRef.current);
        timerRef.current = setTimeout(() => flush(), SAVE_DELAY);
    }, [flush]);

    const reset = useCallback((nextContent) => {
        clearTimeout(timerRef.current);
        pagesRef.current = nextContent.pages;
        versionRef.current = nextContent.version;
        dirtyRef.current = false;
        blockedRef.current = false;
        setPagesState(nextContent.pages);
        setWarnings(nextContent.warnings ?? []);
        setError(null);
        setStatus('saved');
    }, []);

    useEffect(() => {
        const beforeUnload = (event) => {
            if (dirtyRef.current || runningRef.current) {
                flush();
                event.preventDefault();
            }
        };

        window.addEventListener('beforeunload', beforeUnload);

        return () => {
            window.removeEventListener('beforeunload', beforeUnload);
            clearTimeout(timerRef.current);
        };
    }, [flush]);

    return { pages, pagesRef, setPages, status, error, warnings, flush, reset };
}
