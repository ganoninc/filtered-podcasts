import type { FilterCategory, FilterOperator, FilterRule } from '../../lib/types';

export const operatorChoices: Record<FilterCategory, { value: FilterOperator; label: string }[]> = {
  title: [
    { value: 'contains', label: 'Contains' },
    { value: 'not_contains', label: 'Doesn’t contain' },
  ],
  duration: [
    { value: 'longer_than', label: 'Longer than' },
    { value: 'shorter_than', label: 'Shorter than' },
  ],
};

export const createEmptyRule = (): FilterRule => ({
  category: 'title',
  operator: 'contains',
  value: '',
  combinator: 'and',
});
