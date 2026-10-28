import { describeRule, groupRules } from '../../lib/format';
import type { FilterRule } from '../../lib/types';

interface FilterChipListProps {
  filters: FilterRule[];
}

export function FilterChipList({ filters }: FilterChipListProps) {
  if (filters.length === 0) {
    return <span className="subtle">No rules — every episode is kept</span>;
  }

  return (
    <div className="chips">
      {groupRules(filters).map(({ category, rules }, groupIndex) => (
        <span key={category} className="chips">
          {groupIndex > 0 ? <span className="chip-join">and</span> : null}
          {rules.map(({ rule, index }, position) => (
            <span key={index} className="chips">
              {position > 0 ? <span className="chip-join">{rule.combinator}</span> : null}
              <span className="chip">{describeRule(rule)}</span>
            </span>
          ))}
        </span>
      ))}
    </div>
  );
}
