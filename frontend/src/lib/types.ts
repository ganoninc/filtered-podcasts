export type FilterCategory = 'title' | 'duration';
export type FilterOperator = 'contains' | 'not_contains' | 'shorter_than' | 'longer_than';
export type FilterCombinator = 'and' | 'or';

export interface FilterRule {
  category: FilterCategory;
  operator: FilterOperator;
  value: string | number;
  combinator: FilterCombinator;
}

export interface Feed {
  id: number;
  title: string;
  slug: string;
  sourceUrl: string;
  description: string | null;
  artworkUrl: string | null;
  filters: FilterRule[];
  cacheGeneratedAt: string | null;
  publicUrl: string;
}

export interface PublicFeed {
  id: number;
  title: string;
  slug: string;
  sourceUrl: string;
  description: string | null;
  artworkUrl: string | null;
  filters: FilterRule[];
  publicUrl: string;
}

export interface BootstrapData {
  user: { email: string; roles: string[] } | null;
  csrf: {
    login: string;
    logout: string;
    adminApi: string;
  };
  auth: {
    lastUsername: string;
    lastError: string | null;
  };
}

export interface DirectoryResult {
  title: string;
  author: string;
  feedUrl: string;
  artworkUrl: string | null;
  appleUrl: string | null;
}
