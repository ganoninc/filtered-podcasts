import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { AdminFeedCard } from '../../components/feeds/AdminFeedCard';
import { EmptyState } from '../../components/ui/EmptyState';
import { Icon } from '../../components/ui/Icon';
import { ScreenMessage } from '../../components/ui/ScreenMessage';
import { useToast } from '../../components/ui/Toast';
import { api } from '../../lib/api';
import type { BootstrapData, Feed } from '../../lib/types';

interface AdminDashboardPageProps {
  bootstrap: BootstrapData;
}

export function AdminDashboardPage({ bootstrap }: AdminDashboardPageProps) {
  const [feeds, setFeeds] = useState<Feed[]>([]);
  const [state, setState] = useState<'loading' | 'ready' | 'error'>('loading');
  const notify = useToast();

  useEffect(() => {
    api.adminFeeds()
      .then((items) => {
        setFeeds(items);
        setState('ready');
      })
      .catch(() => setState('error'));
  }, []);

  const handleDelete = async (feed: Feed) => {
    try {
      await api.deleteFeed(feed.id, bootstrap.csrf.adminApi);
      setFeeds((current) => current.filter((item) => item.id !== feed.id));
      notify(`“${feed.title}” deleted`);
    } catch {
      notify('Couldn’t delete the feed. Please try again.', 'error');
    }
  };

  const newFeedButton = (
    <Link className="btn btn-primary" to="/admin/feeds/new">
      <Icon name="plus" />
      New feed
    </Link>
  );

  return (
    <>
      <header className="page-header">
        <div>
          <h1 className="page-title">Your feeds</h1>
          <p className="page-lead">Each feed republishes a podcast with only the episodes that match its rules.</p>
        </div>
        {state === 'ready' && feeds.length > 0 ? newFeedButton : null}
      </header>

      {state === 'loading' ? <ScreenMessage title="Loading feeds" loading /> : null}

      {state === 'error' ? (
        <ScreenMessage
          title="Feeds unavailable"
          message="We couldn’t load your feeds. Please try again."
          action={<button type="button" className="btn btn-secondary" onClick={() => window.location.reload()}>Try again</button>}
        />
      ) : null}

      {state === 'ready' && feeds.length === 0 ? (
        <EmptyState
          icon="filter"
          title="Create your first filtered feed"
          message="Pick a podcast, add a few rules, and get a subscription link that only includes the episodes you want."
          action={newFeedButton}
        />
      ) : null}

      {state === 'ready' && feeds.length > 0 ? (
        <div className="card feed-list">
          {feeds.map((feed) => <AdminFeedCard key={feed.id} feed={feed} onDelete={handleDelete} />)}
        </div>
      ) : null}
    </>
  );
}
