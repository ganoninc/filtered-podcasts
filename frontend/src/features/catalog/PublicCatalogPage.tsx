import { useEffect, useState } from 'react';
import { PublicFeedCard } from '../../components/feeds/PublicFeedCard';
import { EmptyState } from '../../components/ui/EmptyState';
import { ScreenMessage } from '../../components/ui/ScreenMessage';
import { api } from '../../lib/api';
import type { PublicFeed } from '../../lib/types';

export function PublicCatalogPage() {
  const [feeds, setFeeds] = useState<PublicFeed[]>([]);
  const [state, setState] = useState<'loading' | 'ready' | 'error'>('loading');

  useEffect(() => {
    api.publicFeeds()
      .then((items) => {
        setFeeds(items);
        setState('ready');
      })
      .catch(() => setState('error'));
  }, []);

  return (
    <>
      <section className="hero">
        <span className="eyebrow">Filtered Podcasts</span>
        <h1>Your favourite shows, minus the episodes you skip.</h1>
        <p>Each feed below is a trimmed copy of a podcast that only keeps the episodes matching its rules. Subscribe in Apple Podcasts or paste the link into any podcast app.</p>
      </section>

      {state === 'loading' ? <ScreenMessage title="Loading feeds" loading /> : null}

      {state === 'error' ? (
        <ScreenMessage
          title="Feeds unavailable"
          message="We couldn’t load the feed list. Please try again in a moment."
          action={<button type="button" className="btn btn-secondary" onClick={() => window.location.reload()}>Try again</button>}
        />
      ) : null}

      {state === 'ready' && feeds.length === 0 ? (
        <EmptyState title="No feeds yet" message="Filtered feeds will show up here once they’re published." />
      ) : null}

      {state === 'ready' && feeds.length > 0 ? (
        <div className="feed-grid">
          {feeds.map((feed) => <PublicFeedCard key={feed.id} feed={feed} />)}
        </div>
      ) : null}
    </>
  );
}
