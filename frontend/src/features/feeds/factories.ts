import type { Feed } from '../../lib/types';

export const createEmptyFeed = (): Omit<Feed, 'id' | 'slug' | 'cacheGeneratedAt' | 'publicUrl'> => ({
  title: '',
  sourceUrl: '',
  description: '',
  artworkUrl: null,
  filters: [],
});
