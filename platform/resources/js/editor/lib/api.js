/**
 * Appels à l'API JSON de l'éditeur (session de l'administration + jeton CSRF).
 */
export class ApiError extends Error {
    constructor(message, status, data = {}) {
        super(message);
        this.status = status;
        this.data = data;
    }
}

export function createApi(baseUrl) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    async function request(method, path = '', body = undefined) {
        const isForm = body instanceof FormData;
        let response;

        try {
            response = await fetch(baseUrl + path, {
                method,
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                    ...(body !== undefined && !isForm ? { 'Content-Type': 'application/json' } : {}),
                },
                body: body === undefined ? undefined : isForm ? body : JSON.stringify(body),
            });
        } catch {
            throw new ApiError('Connexion impossible. Vérifiez votre réseau.', 0);
        }

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            const firstError = data.errors ? Object.values(data.errors).flat()[0] : null;
            const message = firstError ?? data.message ?? `Erreur ${response.status}`;

            throw new ApiError(response.status === 419 ? 'Votre session a expiré : rechargez la page.' : message, response.status, data);
        }

        return data;
    }

    return {
        bootstrap: () => request('GET'),
        savePages: (version, pages) => request('PUT', '/pages', { version, pages }),
        createDraft: () => request('POST', '/draft'),
        preview: () => request('POST', '/preview'),
        media: () => request('GET', '/media'),
        upload: (file) => {
            const form = new FormData();
            form.append('file', file);

            return request('POST', '/media', form);
        },
        revisions: () => request('GET', '/revisions'),
        revision: (id) => request('GET', `/revisions/${id}`),
        restore: (id) => request('POST', `/revisions/${id}/restore`),
        aiTransform: (payload) => request('POST', '/ai/transform', payload),
        aiWrite: (payload) => request('POST', '/ai/write', payload),
        aiSeo: (page) => request('POST', '/ai/seo', { page }),
    };
}
