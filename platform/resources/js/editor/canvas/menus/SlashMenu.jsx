import { Text } from '@mantine/core';
import { forwardRef, useEffect, useImperativeHandle, useRef, useState } from 'react';

/**
 * Liste du menu « / », groupée (Texte, Sections du thème, IA), navigable au clavier.
 */
const SlashMenu = forwardRef(function SlashMenu({ items, command }, ref) {
    const [selected, setSelected] = useState(0);
    const listRef = useRef(null);

    useEffect(() => setSelected(0), [items]);

    useEffect(() => {
        listRef.current?.querySelector(`[data-index="${selected}"]`)?.scrollIntoView({ block: 'nearest' });
    }, [selected]);

    useImperativeHandle(ref, () => ({
        onKeyDown: (event) => {
            if (event.key === 'ArrowDown') {
                setSelected((index) => (index + 1) % Math.max(items.length, 1));

                return true;
            }

            if (event.key === 'ArrowUp') {
                setSelected((index) => (index - 1 + items.length) % Math.max(items.length, 1));

                return true;
            }

            if (event.key === 'Enter' || event.key === 'Tab') {
                if (items[selected]) command(items[selected]);

                return true;
            }

            return false;
        },
    }));

    if (items.length === 0) {
        return (
            <div className="ed-slash">
                <Text size="sm" c="dimmed" p="sm">Aucun bloc ne correspond.</Text>
            </div>
        );
    }

    let lastGroup = null;

    return (
        <div className="ed-slash" ref={listRef} role="listbox">
            {items.map((item, index) => {
                const showGroup = item.group !== lastGroup;
                lastGroup = item.group;
                const Icon = item.icon;

                return (
                    <div key={item.id}>
                        {showGroup && <div className="ed-slash-group">{item.group}</div>}
                        <button
                            type="button"
                            data-index={index}
                            className={`ed-slash-item${index === selected ? ' is-selected' : ''}`}
                            onMouseEnter={() => setSelected(index)}
                            onMouseDown={(event) => {
                                event.preventDefault();
                                command(item);
                            }}
                        >
                            <span className={`ed-slash-icon${item.accent ? ' is-accent' : ''}`}><Icon size={18} stroke={1.6} /></span>
                            <span className="ed-slash-text">
                                <span className="ed-slash-title">{item.title}</span>
                                <span className="ed-slash-description">{item.description}</span>
                            </span>
                        </button>
                    </div>
                );
            })}
        </div>
    );
});

export default SlashMenu;
