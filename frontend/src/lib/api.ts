import type { BootstrapData, DirectoryResult, Feed, PublicFeed } from './types';

async function request<T>(input: RequestInfo, init?: RequestInit): Promise<T> {
  const response = await fetch(input, {
    ...init,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      ...(init?.headers ?? {}),
    },
  });

  // An expired session makes the firewall redirect API calls to the HTML login page.
  if (response.redirected && new URL(response.url).pathname === '/login') {
    window.location.assign('/login');
    throw { message: 'Your session has expired. Please sign in again.' };
  }

  const text = await response.text();
  let payload = null;
  try {
    payload = text ? JSON.parse(text) : null;
  } catch {
    throw { message: 'The server returned an unexpected response.' };
  }

  if (!response.ok) {
    throw payload ?? { message: 'Request failed.' };
  }

  return payload as T;
}

export const api = {
  bootstrap: () => request<BootstrapData>('/api/bootstrap'),
  publicFeeds: async () => (await request<{ feeds: PublicFeed[] }>('/api/public/feeds')).feeds,
  adminFeeds: async () => (await request<{ feeds: Feed[] }>('/api/admin/feeds')).feeds,
  adminFeed: async (id: number) => (await request<{ feed: Feed }>(`/api/admin/feeds/${id}`)).feed,
  saveFeed: async (feed: Partial<Feed>, csrfToken: string, id?: number) => {
    const method = id ? 'PUT' : 'POST';
    const url = id ? `/api/admin/feeds/${id}` : '/api/admin/feeds';

    return (await request<{ feed: Feed }>(url, {
      method,
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken,
      },
      body: JSON.stringify(feed),
    })).feed;
  },
  deleteFeed: async (id: number, csrfToken: string) => {
    await request(`/api/admin/feeds/${id}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-Token': csrfToken,
      },
    });
  },
  searchSources: async (query: string) => (await request<{ results: DirectoryResult[] }>(`/api/admin/feeds/search/source?q=${encodeURIComponent(query)}`)).results,
};
