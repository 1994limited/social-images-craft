/*
    The little of axios the shared pages use ($axios.get/post/patch/delete,
    resolving to { data } and rejecting with { response: { status, data } }),
    over fetch with Craft's CSRF token.
*/
async function request(method, url, data, config = {}) {
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': window.Craft?.csrfTokenValue ?? '',
    };

    let body;

    if (data instanceof FormData) {
        body = data;
    } else if (data !== undefined) {
        headers['Content-Type'] = 'application/json';
        body = JSON.stringify(data);
    }

    if (config.params) {
        const query = new URLSearchParams(Object.entries(config.params).filter(([, value]) => value !== undefined && value !== null));
        url += (url.includes('?') ? '&' : '?') + query;
    }

    const response = await fetch(url, { method, headers, body, credentials: 'same-origin' });
    const text = await response.text();
    let json = null;

    try {
        json = text ? JSON.parse(text) : null;
    } catch {
        json = { message: text.slice(0, 300) };
    }

    if (!response.ok) {
        const error = new Error(json?.message ?? response.statusText);
        error.response = { status: response.status, data: json ?? {} };
        throw error;
    }

    return { data: json, status: response.status };
}

export default {
    get: (url, config) => request('GET', url, undefined, config),
    delete: (url, config) => request('DELETE', url, undefined, config),
    post: (url, data, config) => request('POST', url, data ?? {}, config),
    patch: (url, data, config) => request('PATCH', url, data ?? {}, config),
};
