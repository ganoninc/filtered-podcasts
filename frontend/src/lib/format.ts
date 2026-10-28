import type { FilterCategory, FilterRule } from './types';

export function describeRule(rule: FilterRule): string {
  const value = String(rule.value).trim();

  switch (rule.operator) {
    case 'contains':
      return `Title contains “${value}”`;
    case 'not_contains':
      return `Title doesn’t contain “${value}”`;
    case 'longer_than':
      return `Longer than ${value} min`;
    case 'shorter_than':
      return `Shorter than ${value} min`;
  }
}

/**
 * Rules combine with their own combinator inside a category; categories are always ANDed.
 * Mirrors FeedFilterService::itemMatches().
 */
export function groupRules(filters: FilterRule[]): { category: FilterCategory; rules: { rule: FilterRule; index: number }[] }[] {
  const categories: FilterCategory[] = ['title', 'duration'];

  return categories
    .map((category) => ({
      category,
      rules: filters
        .map((rule, index) => ({ rule, index }))
        .filter(({ rule }) => rule.category === category),
    }))
    .filter((group) => group.rules.length > 0);
}

export function summarizeRules(filters: FilterRule[]): string | null {
  const complete = filters.filter((rule) => String(rule.value).trim() !== '');
  if (complete.length === 0) {
    return null;
  }

  const parts = groupRules(complete).map(({ rules }) => rules
    .map(({ rule }, position) => {
      const text = describeRuleClause(rule);

      return position === 0 ? text : `${rule.combinator === 'or' ? 'or' : 'and'} ${text}`;
    })
    .join(' '));

  return `Keeps episodes that ${parts.join(', and ')}.`;
}

function describeRuleClause(rule: FilterRule): string {
  const value = String(rule.value).trim();

  switch (rule.operator) {
    case 'contains':
      return `have “${value}” in the title`;
    case 'not_contains':
      return `don’t have “${value}” in the title`;
    case 'longer_than':
      return `are longer than ${value} min`;
    case 'shorter_than':
      return `are shorter than ${value} min`;
  }
}

export function timeAgo(iso: string | null): string | null {
  if (!iso) {
    return null;
  }

  const seconds = Math.round((Date.now() - new Date(iso).getTime()) / 1000);
  const units: [Intl.RelativeTimeFormatUnit, number][] = [
    ['day', 86400],
    ['hour', 3600],
    ['minute', 60],
  ];

  const formatter = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });
  for (const [unit, size] of units) {
    if (seconds >= size) {
      return formatter.format(-Math.floor(seconds / size), unit);
    }
  }

  return 'just now';
}

export function hostOf(url: string): string {
  try {
    return new URL(url).host.replace(/^www\./, '');
  } catch {
    return url;
  }
}

/** Opens the feed in Apple Podcasts (and most iOS/macOS podcast apps). */
export function podcastAppUrl(url: string): string {
  return url.replace(/^https?:\/\//, 'podcast://');
}
