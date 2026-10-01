import { createContext, useContext } from 'react';

/**
 * Données et services partagés par toute l'application (y compris les vues de nœuds de l'éditeur).
 */
export const WorkspaceContext = createContext(null);

export function useWorkspaceContext() {
    return useContext(WorkspaceContext);
}
