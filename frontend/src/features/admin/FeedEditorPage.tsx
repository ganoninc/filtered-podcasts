import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { FeedArtwork } from '../../components/feeds/FeedArtwork';
import { PodcastSearchResultCard } from '../../components/feeds/PodcastSearchResultCard';
import { CopyButton } from '../../components/ui/CopyButton';
import { Icon } from '../../components/ui/Icon';
import { ScreenMessage } from '../../components/ui/ScreenMessage';
import { useToast } from '../../components/ui/Toast';
import { createEmptyFeed } from '../feeds/factories';
import { createEmptyRule, operatorChoices } from '../feeds/constants';
import { api } from '../../lib/api';
import { hostOf, summarizeRules } from '../../lib/format';
import type { BootstrapData, DirectoryResult, Feed, FilterCategory, FilterOperator, FilterRule } from '../../lib/types';

interface FeedEditorPageProps {
  bootstrap: BootstrapData;
}

type FormState = Omit<Feed, 'id' | 'slug' | 'cacheGeneratedAt' | 'publicUrl'>;

interface SourceInfo {
  title: string;
  subtitle: string;
  artworkUrl: string | null;
}

type SourceMode = 'search' | 'manual' | 'selected';

const ruleGroups: { category: FilterCategory; title: string; empty: string; addLabel: string }[] = [
  { category: 'title', title: 'Episode title', empty: 'Any title is kept.', addLabel: 'Add title rule' },
  { category: 'duration', title: 'Episode length', empty: 'Any length is kept.', addLabel: 'Add length rule' },
];

export function FeedEditorPage({ bootstrap }: FeedEditorPageProps) {
  const params = useParams();
  const isEditing = Boolean(params.id);
  const navigate = useNavigate();
  const notify = useToast();

  const [form, setForm] = useState<FormState>(createEmptyFeed());
  const [publicUrl, setPublicUrl] = useState<string | null>(null);
  const [loadState, setLoadState] = useState<'loading' | 'ready' | 'error'>(isEditing ? 'loading' : 'ready');
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const [sourceMode, setSourceMode] = useState<SourceMode>('search');
  const [sourceInfo, setSourceInfo] = useState<SourceInfo | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState<DirectoryResult[]>([]);
  const [searchState, setSearchState] = useState<'idle' | 'searching' | 'done' | 'error'>('idle');

  useEffect(() => {
    if (!params.id) {
      return;
    }

    api.adminFeed(Number(params.id))
      .then((feed) => {
        setForm({
          title: feed.title,
          sourceUrl: feed.sourceUrl,
          description: feed.description ?? '',
          artworkUrl: feed.artworkUrl,
          filters: feed.filters,
        });
        setPublicUrl(feed.publicUrl);
        setSourceInfo({ title: hostOf(feed.sourceUrl), subtitle: feed.sourceUrl, artworkUrl: feed.artworkUrl });
        setSourceMode('selected');
        setLoadState('ready');
      })
      .catch(() => setLoadState('error'));
  }, [params.id]);

  const updateRule = (index: number, patch: Partial<FilterRule>) => {
    setForm((current) => ({
      ...current,
      filters: current.filters.map((rule, ruleIndex) => (ruleIndex === index ? { ...rule, ...patch } : rule)),
    }));
  };

  const addRule = (category: FilterCategory) => {
    const rule: FilterRule = {
      ...createEmptyRule(),
      category,
      operator: operatorChoices[category][0].value,
      value: '',
    };
    setForm((current) => ({ ...current, filters: [...current.filters, rule] }));
  };

  const removeRule = (index: number) => {
    setForm((current) => ({ ...current, filters: current.filters.filter((_, ruleIndex) => ruleIndex !== index) }));
    setErrors({});
  };

  const runSearch = async () => {
    const query = searchQuery.trim();
    if (!query) {
      return;
    }

    setSearchState('searching');
    try {
      setSearchResults(await api.searchSources(query));
      setSearchState('done');
    } catch {
      setSearchResults([]);
      setSearchState('error');
    }
  };

  const selectResult = (result: DirectoryResult) => {
    setForm((current) => ({
      ...current,
      title: current.title || result.title,
      sourceUrl: result.feedUrl,
      artworkUrl: result.artworkUrl,
    }));
    setSourceInfo({ title: result.title, subtitle: result.author, artworkUrl: result.artworkUrl });
    setSourceMode('selected');
    setErrors(({ sourceUrl, ...rest }) => rest);
  };

  const save = async (event: FormEvent) => {
    event.preventDefault();
    setSaving(true);
    setErrors({});

    try {
      await api.saveFeed({
        title: form.title,
        sourceUrl: form.sourceUrl,
        description: form.description,
        filters: form.filters,
      }, bootstrap.csrf.adminApi, params.id ? Number(params.id) : undefined);
      notify(isEditing ? 'Changes saved' : 'Feed created');
      navigate('/admin/feeds');
    } catch (error) {
      const payload = error as { errors?: Record<string, string>; message?: string };
      setErrors(payload.errors ?? { global: payload.message ?? 'Couldn’t save the feed. Please try again.' });
      if (payload.errors?.sourceUrl && sourceMode === 'selected') {
        setSourceMode('manual');
      }
    } finally {
      setSaving(false);
    }
  };

  if (loadState === 'loading') {
    return <ScreenMessage title="Loading feed" loading />;
  }

  if (loadState === 'error') {
    return (
      <ScreenMessage
        title="Feed not found"
        message="This feed may have been deleted."
        action={<Link className="btn btn-secondary" to="/admin/feeds">Back to feeds</Link>}
      />
    );
  }

  const summary = summarizeRules(form.filters);
  const hasFieldErrors = Object.keys(errors).some((key) => key !== 'global');

  return (
    <div className="editor">
      <div>
        <Link className="back-link" to="/admin/feeds"><Icon name="arrowLeft" /> All feeds</Link>
        <header className="page-header">
          <div>
            <h1 className="page-title">{isEditing ? form.title || 'Edit feed' : 'New filtered feed'}</h1>
            <p className="page-lead">
              {isEditing ? 'Changes apply to subscribers the next time their app refreshes.' : 'Pick a podcast, decide which episodes to keep, and get a link to subscribe.'}
            </p>
          </div>
        </header>
      </div>

      {isEditing && publicUrl ? (
        <section className="card panel">
          <div className="panel-head">
            <h2 className="panel-title">Subscription link</h2>
            <p className="panel-desc">Paste this into any podcast app. It stays the same when you edit rules.</p>
          </div>
          <div className="copy-field">
            <input className="input" value={publicUrl} readOnly aria-label="Subscription link" onFocus={(event) => event.target.select()} />
            <CopyButton value={publicUrl} label="Copy" />
          </div>
        </section>
      ) : null}

      <form onSubmit={save} className="form" noValidate>
        <section className="card panel">
          <div className="panel-head">
            <h2 className="panel-title">1. Source podcast</h2>
            <p className="panel-desc">The show whose episodes you want to filter.</p>
          </div>

          {sourceMode === 'selected' && sourceInfo ? (
            <div className="selected-source">
              <FeedArtwork artworkUrl={sourceInfo.artworkUrl} />
              <div className="selected-source-main">
                <strong className="truncate">{sourceInfo.title}</strong>
                <span className="subtle truncate">{sourceInfo.subtitle}</span>
              </div>
              <button type="button" className="btn btn-secondary btn-sm" onClick={() => setSourceMode('search')}>Change</button>
            </div>
          ) : null}

          {sourceMode === 'search' ? (
            <div className="fields">
              <div className="search-form">
                <div className="input-group">
                  <span className="input-icon"><Icon name="search" /></span>
                  <input
                    className="input has-icon"
                    value={searchQuery}
                    onChange={(event) => setSearchQuery(event.target.value)}
                    onKeyDown={(event) => {
                      if (event.key === 'Enter') {
                        event.preventDefault();
                        runSearch();
                      }
                    }}
                    placeholder="Search Apple Podcasts by show or author"
                    aria-label="Search podcasts"
                    autoFocus={!isEditing}
                  />
                </div>
                <button type="button" className="btn btn-secondary" onClick={runSearch} disabled={searchState === 'searching' || !searchQuery.trim()}>
                  {searchState === 'searching' ? <span className="spinner small" aria-hidden="true" /> : null}
                  Search
                </button>
              </div>

              {searchState === 'done' && searchResults.length === 0 ? <p className="subtle">No podcasts found for “{searchQuery.trim()}”.</p> : null}
              {searchState === 'error' ? <p className="field-error">Search is unavailable right now. You can paste an RSS URL instead.</p> : null}

              {searchResults.length > 0 ? (
                <div className="results">
                  {searchResults.map((result) => (
                    <PodcastSearchResultCard key={`${result.feedUrl}-${result.title}`} result={result} onUse={selectResult} />
                  ))}
                </div>
              ) : null}

              <p className="subtle">
                Know the feed URL?{' '}
                <button type="button" className="link-button" onClick={() => setSourceMode('manual')}>Paste it instead</button>
                {sourceInfo ? (
                  <>
                    {' · '}
                    <button type="button" className="link-button" onClick={() => setSourceMode('selected')}>Keep current source</button>
                  </>
                ) : null}
              </p>
            </div>
          ) : null}

          {sourceMode === 'manual' ? (
            <div className="fields">
              <label className="field">
                <span className="field-label">RSS feed URL</span>
                <input
                  className="input"
                  type="url"
                  inputMode="url"
                  value={form.sourceUrl}
                  onChange={(event) => setForm({ ...form, sourceUrl: event.target.value })}
                  placeholder="https://example.com/podcast.xml"
                  aria-invalid={Boolean(errors.sourceUrl)}
                  autoFocus
                />
                {errors.sourceUrl ? <span className="field-error">{errors.sourceUrl}</span> : null}
              </label>
              <p className="subtle">
                <button type="button" className="link-button" onClick={() => setSourceMode(sourceInfo ? 'selected' : 'search')}>
                  {sourceInfo ? 'Back to selected podcast' : 'Search instead'}
                </button>
              </p>
            </div>
          ) : null}

          {sourceMode !== 'manual' && errors.sourceUrl ? <span className="field-error">{errors.sourceUrl}</span> : null}
        </section>

        <section className="card panel">
          <div className="panel-head">
            <h2 className="panel-title">2. Details</h2>
            <p className="panel-desc">How the filtered feed appears in podcast apps.</p>
          </div>
          <div className="fields">
            <label className="field">
              <span className="field-label">Title</span>
              <input
                className="input"
                value={form.title}
                onChange={(event) => setForm({ ...form, title: event.target.value })}
                placeholder="e.g. Radiolab – long episodes only"
                aria-invalid={Boolean(errors.title)}
              />
              {errors.title ? <span className="field-error">{errors.title}</span> : null}
            </label>
            <label className="field">
              <span className="field-label">Description <span className="optional">(optional)</span></span>
              <textarea
                className="textarea"
                value={form.description ?? ''}
                onChange={(event) => setForm({ ...form, description: event.target.value })}
                placeholder="What makes this version different?"
              />
            </label>
          </div>
        </section>

        <section className="card panel">
          <div className="panel-head">
            <h2 className="panel-title">3. Episodes to keep</h2>
            <p className="panel-desc">An episode is kept only if it passes both groups below. Leave a group empty to ignore it.</p>
          </div>

          <div className="rule-groups">
            {ruleGroups.map((group) => {
              const rules = form.filters
                .map((rule, index) => ({ rule, index }))
                .filter(({ rule }) => rule.category === group.category);

              return (
                <div className="rule-group" key={group.category}>
                  <div className="rule-group-head">
                    <h3 className="rule-group-title">{group.title}</h3>
                    <button type="button" className="btn btn-ghost btn-sm" onClick={() => addRule(group.category)}>
                      <Icon name="plus" />
                      {group.addLabel}
                    </button>
                  </div>

                  {rules.length === 0 ? <p className="subtle">{group.empty}</p> : (
                    <div className="rule-list">
                      {rules.map(({ rule, index }, position) => {
                        const error = errors[`filters.${index}.value`] ?? errors[`filters.${index}.operator`];

                        return (
                          <div key={index} className="rule-list">
                            {position > 0 ? (
                              <div className="combinator" role="group" aria-label="Combine with previous rule">
                                {(['and', 'or'] as const).map((combinator) => (
                                  <button
                                    key={combinator}
                                    type="button"
                                    aria-pressed={rule.combinator === combinator}
                                    onClick={() => updateRule(index, { combinator })}
                                  >
                                    {combinator.toUpperCase()}
                                  </button>
                                ))}
                              </div>
                            ) : null}
                            <div className="rule-row">
                              <select
                                className="select"
                                value={rule.operator}
                                onChange={(event) => updateRule(index, { operator: event.target.value as FilterOperator })}
                                aria-label="Condition"
                              >
                                {operatorChoices[rule.category].map((choice) => (
                                  <option key={choice.value} value={choice.value}>{choice.label}</option>
                                ))}
                              </select>
                              {rule.category === 'duration' ? (
                                <div className="input-group">
                                  <input
                                    className="input"
                                    type="number"
                                    min={0}
                                    inputMode="numeric"
                                    value={String(rule.value)}
                                    onChange={(event) => updateRule(index, { value: event.target.value === '' ? '' : Number(event.target.value) })}
                                    placeholder="30"
                                    aria-label="Minutes"
                                    aria-invalid={Boolean(error)}
                                  />
                                  <span className="input-suffix">min</span>
                                </div>
                              ) : (
                                <input
                                  className="input"
                                  value={String(rule.value)}
                                  onChange={(event) => updateRule(index, { value: event.target.value })}
                                  placeholder="e.g. interview"
                                  aria-label="Text"
                                  aria-invalid={Boolean(error)}
                                />
                              )}
                              <button type="button" className="btn btn-ghost btn-icon danger" onClick={() => removeRule(index)} aria-label="Remove rule" title="Remove rule">
                                <Icon name="x" />
                              </button>
                              {error ? <span className="field-error">{error}</span> : null}
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  )}
                </div>
              );
            })}
          </div>

          <div className="rule-summary">
            <Icon name="filter" />
            <span>{summary ?? 'No rules yet — every episode from the source will be kept.'}</span>
          </div>
        </section>

        {errors.global ? <div className="banner banner-error" role="alert"><Icon name="alert" />{errors.global}</div> : null}
        {hasFieldErrors ? <div className="banner banner-error" role="alert"><Icon name="alert" />Please fix the highlighted fields.</div> : null}

        <div className="action-bar">
          <Link className="btn btn-ghost" to="/admin/feeds">Cancel</Link>
          <button type="submit" className="btn btn-primary" disabled={saving}>
            {saving ? <span className="spinner small" aria-hidden="true" /> : null}
            {saving ? 'Saving…' : isEditing ? 'Save changes' : 'Create feed'}
          </button>
        </div>
      </form>
    </div>
  );
}
