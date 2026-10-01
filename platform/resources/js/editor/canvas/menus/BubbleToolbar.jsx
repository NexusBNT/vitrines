import { ActionIcon, Button, Divider, Group, Loader, Menu, Paper, ScrollArea, Stack, Text, Textarea, Tooltip } from '@mantine/core';
import {
    IconBold,
    IconCheck,
    IconChevronDown,
    IconItalic,
    IconLink,
    IconLinkOff,
    IconRefresh,
    IconRowInsertBottom,
    IconSparkles,
    IconStrikethrough,
    IconUnderline,
    IconX,
} from '@tabler/icons-react';
import { TextSelection } from '@tiptap/pm/state';
import { useEditorState } from '@tiptap/react';
import { BubbleMenu } from '@tiptap/react/menus';
import { useRef, useState } from 'react';
import LinkTargetInput from '../../components/LinkTargetInput';
import { AI_ACTIONS } from '../../lib/aiActions';
import { textToParagraphs } from '../../lib/convert';
import { useWorkspaceContext } from '../../lib/context';

const BLOCK_TYPES = [
    { id: 'paragraph', label: 'Texte', isActive: (editor) => editor.isActive('paragraph'), run: (chain) => chain.setParagraph() },
    { id: 'h2', label: 'Intertitre', isActive: (editor) => editor.isActive('heading', { level: 2 }), run: (chain) => chain.setHeading({ level: 2 }) },
    { id: 'h3', label: 'Sous-titre', isActive: (editor) => editor.isActive('heading', { level: 3 }), run: (chain) => chain.setHeading({ level: 3 }) },
    { id: 'bullets', label: 'Liste à puces', isActive: (editor) => editor.isActive('bulletList'), run: (chain) => chain.toggleBulletList() },
    { id: 'numbers', label: 'Liste numérotée', isActive: (editor) => editor.isActive('orderedList'), run: (chain) => chain.toggleOrderedList() },
    { id: 'quote', label: 'Citation', isActive: (editor) => editor.isActive('blockquote'), run: (chain) => chain.toggleBlockquote() },
];

/**
 * Barre flottante sur une sélection de texte : type de bloc, mise en forme, lien, et réécriture par l'IA.
 */
export default function BubbleToolbar({ editor }) {
    const { api, aiAvailable, currentKey, notifyError } = useWorkspaceContext();
    const [mode, setMode] = useState('toolbar');
    const [ai, setAi] = useState(null);
    const [instruction, setInstruction] = useState('');
    const modeRef = useRef(mode);
    modeRef.current = mode;

    const state = useEditorState({
        editor,
        selector: ({ editor: current }) => ({
            bold: current.isActive('bold'),
            italic: current.isActive('italic'),
            underline: current.isActive('underline'),
            strike: current.isActive('strike'),
            link: current.getAttributes('link').href ?? null,
            block: BLOCK_TYPES.find((type) => type.isActive(current))?.label ?? 'Texte',
        }),
    });

    const reset = () => {
        setMode('toolbar');
        setAi(null);
    };

    const runAi = async (action, extra = null) => {
        const { from, to } = editor.state.selection;
        const text = editor.state.doc.textBetween(from, to, '\n\n', ' ');

        setMode('ai');
        setAi({ loading: true, from, to, action, extra, result: null });

        try {
            const response = await api.aiTransform({ action, text, instruction: extra, page: currentKey });
            setAi({ loading: false, from, to, action, extra, result: response.text });
        } catch (exception) {
            notifyError('L\'IA n\'a pas pu proposer de texte', exception.message);
            reset();
        }
    };

    const applyAi = (insertBelow = false) => {
        const { from, to, result } = ai;
        const paragraphs = textToParagraphs(result);
        const $from = editor.state.doc.resolve(from);
        const singleBlock = $from.sameParent(editor.state.doc.resolve(to)) && paragraphs.length === 1;

        if (insertBelow) {
            const end = editor.state.doc.resolve(to).after(1);
            editor.chain().focus().insertContentAt(end, paragraphs).run();
        } else if (singleBlock) {
            editor.chain().focus().insertContentAt({ from, to }, paragraphs[0].content ?? []).run();
        } else {
            editor.chain().focus().insertContentAt({ from, to }, paragraphs).run();
        }

        reset();
    };

    const shouldShow = ({ editor: current, state: editorState }) => {
        if (modeRef.current !== 'toolbar') return true;

        const { selection } = editorState;

        return current.isEditable && selection instanceof TextSelection && !selection.empty && editorState.doc.textBetween(selection.from, selection.to).trim() !== '';
    };

    return (
        <BubbleMenu editor={editor} shouldShow={shouldShow} options={{ placement: 'top-start', offset: 8 }} className="ed-bubble">
            {mode === 'toolbar' && (
                <Paper shadow="lg" radius="md" p={4} withBorder>
                    <Group gap={2} wrap="nowrap">
                        <Menu withinPortal={false} position="bottom-start" shadow="md">
                            <Menu.Target>
                                <Button variant="subtle" color="gray" size="compact-sm" rightSection={<IconChevronDown size={14} />}>{state.block}</Button>
                            </Menu.Target>
                            <Menu.Dropdown>
                                {BLOCK_TYPES.map((type) => (
                                    <Menu.Item key={type.id} onClick={() => type.run(editor.chain().focus()).run()}>{type.label}</Menu.Item>
                                ))}
                            </Menu.Dropdown>
                        </Menu>
                        <Divider orientation="vertical" />
                        <FormatButton label="Gras (Ctrl+B)" active={state.bold} onClick={() => editor.chain().focus().toggleBold().run()} icon={IconBold} />
                        <FormatButton label="Italique (Ctrl+I)" active={state.italic} onClick={() => editor.chain().focus().toggleItalic().run()} icon={IconItalic} />
                        <FormatButton label="Souligné (Ctrl+U)" active={state.underline} onClick={() => editor.chain().focus().toggleUnderline().run()} icon={IconUnderline} />
                        <FormatButton label="Barré" active={state.strike} onClick={() => editor.chain().focus().toggleStrike().run()} icon={IconStrikethrough} />
                        <FormatButton label={state.link ? 'Modifier le lien' : 'Ajouter un lien'} active={Boolean(state.link)} onClick={() => setMode('link')} icon={IconLink} />
                        {aiAvailable && (
                            <>
                                <Divider orientation="vertical" />
                                <Menu withinPortal={false} position="bottom-end" shadow="md" width={220}>
                                    <Menu.Target>
                                        <Button variant="light" color="violet" size="compact-sm" leftSection={<IconSparkles size={14} />}>IA</Button>
                                    </Menu.Target>
                                    <Menu.Dropdown>
                                        {AI_ACTIONS.map((action) => (
                                            <Menu.Item key={action.id} leftSection={<action.icon size={16} />} onClick={() => runAi(action.id)}>{action.label}</Menu.Item>
                                        ))}
                                        <Menu.Divider />
                                        <Menu.Item leftSection={<IconSparkles size={16} />} onClick={() => setMode('ai-custom')}>Consigne libre…</Menu.Item>
                                    </Menu.Dropdown>
                                </Menu>
                            </>
                        )}
                    </Group>
                </Paper>
            )}

            {mode === 'link' && (
                <Paper shadow="lg" radius="md" p="sm" withBorder w={300}>
                    <LinkEditor
                        href={state.link}
                        onSave={(href) => {
                            editor.chain().focus().extendMarkRange('link').setLink({ href }).run();
                            reset();
                        }}
                        onRemove={() => {
                            editor.chain().focus().extendMarkRange('link').unsetLink().run();
                            reset();
                        }}
                        onCancel={reset}
                    />
                </Paper>
            )}

            {mode === 'ai-custom' && (
                <Paper shadow="lg" radius="md" p="sm" withBorder w={360}>
                    <Stack gap="xs">
                        <Textarea
                            autoFocus
                            label="Consigne pour l'IA"
                            placeholder="Ex. : reformuler pour mettre en avant la propreté du chantier"
                            value={instruction}
                            onChange={(event) => setInstruction(event.currentTarget.value)}
                            autosize
                            minRows={2}
                            maxLength={500}
                        />
                        <Group justify="flex-end" gap="xs">
                            <Button size="xs" variant="default" onClick={reset}>Annuler</Button>
                            <Button size="xs" color="violet" disabled={!instruction.trim()} onClick={() => runAi('custom', instruction)}>Proposer</Button>
                        </Group>
                    </Stack>
                </Paper>
            )}

            {mode === 'ai' && ai && (
                <Paper shadow="lg" radius="md" p="sm" withBorder w={420} className="ed-ai-card">
                    {ai.loading ? (
                        <Group gap="sm"><Loader size="xs" color="violet" type="dots" /><Text size="sm" c="dimmed">L'IA rédige une proposition…</Text></Group>
                    ) : (
                        <Stack gap="xs">
                            <Text size="xs" fw={600} c="violet">Proposition de l'IA</Text>
                            <ScrollArea.Autosize mah={280}>
                                <Text size="sm" style={{ whiteSpace: 'pre-wrap' }}>{ai.result}</Text>
                            </ScrollArea.Autosize>
                            <Group justify="space-between" gap="xs">
                                <Button size="xs" variant="subtle" color="gray" leftSection={<IconRefresh size={14} />} onClick={() => runAi(ai.action, ai.extra)}>Autre proposition</Button>
                                <Group gap={6}>
                                    <Tooltip label="Ignorer"><ActionIcon variant="default" onClick={reset} aria-label="Ignorer"><IconX size={16} /></ActionIcon></Tooltip>
                                    <Tooltip label="Insérer en dessous"><ActionIcon variant="default" onClick={() => applyAi(true)} aria-label="Insérer en dessous"><IconRowInsertBottom size={16} /></ActionIcon></Tooltip>
                                    <Button size="xs" color="violet" leftSection={<IconCheck size={14} />} onClick={() => applyAi(false)}>Remplacer</Button>
                                </Group>
                            </Group>
                        </Stack>
                    )}
                </Paper>
            )}
        </BubbleMenu>
    );
}

function FormatButton({ label, active, onClick, icon: Icon }) {
    return (
        <Tooltip label={label} withinPortal={false}>
            <ActionIcon variant={active ? 'light' : 'subtle'} color={active ? 'indigo' : 'gray'} onClick={onClick} aria-label={label} aria-pressed={active}>
                <Icon size={16} />
            </ActionIcon>
        </Tooltip>
    );
}

function LinkEditor({ href, onSave, onRemove, onCancel }) {
    const [value, setValue] = useState(href ?? 'page:home');

    return (
        <Stack gap="xs">
            <Text size="xs" fw={600}>Lien vers</Text>
            <LinkTargetInput value={value} onChange={setValue} withinPortal={false} />
            <Group justify="space-between" gap="xs">
                {href ? <Button size="xs" variant="subtle" color="red" leftSection={<IconLinkOff size={14} />} onClick={onRemove}>Retirer</Button> : <span />}
                <Group gap="xs">
                    <Button size="xs" variant="default" onClick={onCancel}>Annuler</Button>
                    <Button size="xs" onClick={() => onSave(value)} disabled={!value || /^(https?:\/\/|tel:|mailto:)$/.test(value)}>Appliquer</Button>
                </Group>
            </Group>
        </Stack>
    );
}
