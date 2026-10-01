import { useCallback, useEffect, useLayoutEffect, useRef } from 'react';
import AiFieldMenu from './AiFieldMenu';

/**
 * Champ de texte éditable « en place », qui prend la typographie du site (titre, paragraphe…).
 * Une seule ligne par défaut (Entrée ignorée) ; `multiline` autorise les paragraphes.
 */
export default function InlineText({ value, onChange, placeholder, multiline = false, maxLength, className = '', ai = false, aiLabel }) {
    const ref = useRef(null);

    const resize = useCallback(() => {
        const element = ref.current;

        if (!element) return;

        element.style.height = '0px';
        element.style.height = `${element.scrollHeight}px`;
    }, []);

    useLayoutEffect(resize, [value, resize]);

    // La hauteur dépend de la largeur disponible et de la police du site, chargée après coup.
    useEffect(() => {
        const element = ref.current;
        let lastWidth = element.clientWidth;
        const observer = new ResizeObserver(([entry]) => {
            if (entry.contentRect.width === lastWidth) return;

            lastWidth = entry.contentRect.width;
            requestAnimationFrame(resize);
        });
        observer.observe(element);
        document.fonts?.ready.then(resize);
        document.fonts?.addEventListener('loadingdone', resize);

        return () => {
            observer.disconnect();
            document.fonts?.removeEventListener('loadingdone', resize);
        };
    }, [resize]);

    return (
        <span className={`ed-inline${ai ? ' has-ai' : ''}`}>
            <textarea
                ref={ref}
                rows={1}
                className={`ed-inline-input ${className}`}
                value={value ?? ''}
                placeholder={placeholder}
                maxLength={maxLength}
                spellCheck
                onChange={(event) => onChange(multiline ? event.target.value : event.target.value.replace(/\n/g, ' '))}
                onKeyDown={(event) => {
                    if (event.key === 'Enter' && !multiline) event.preventDefault();
                    if (event.key === 'Escape') event.currentTarget.blur();
                }}
            />
            {ai && <AiFieldMenu value={value ?? ''} onApply={onChange} label={aiLabel} />}
        </span>
    );
}
