import { IconArrowsMaximize, IconArrowsMinimize, IconBriefcase, IconHeart, IconTextSpellcheck, IconWand, IconFeather } from '@tabler/icons-react';

/**
 * Actions de réécriture proposées partout dans l'éditeur (mêmes identifiants que côté serveur).
 */
export const AI_ACTIONS = [
    { id: 'improve', label: 'Améliorer le style', icon: IconWand },
    { id: 'fix', label: 'Corriger l\'orthographe', icon: IconTextSpellcheck },
    { id: 'shorten', label: 'Raccourcir', icon: IconArrowsMinimize },
    { id: 'lengthen', label: 'Développer', icon: IconArrowsMaximize },
    { id: 'simplify', label: 'Simplifier', icon: IconFeather },
    { id: 'warmer', label: 'Ton plus chaleureux', icon: IconHeart },
    { id: 'professional', label: 'Ton plus professionnel', icon: IconBriefcase },
];
