import { useState } from 'react';
import { Link } from 'react-router-dom';
import { CopyButton } from '../ui/CopyButton';
import { Icon } from '../ui/Icon';
import { timeAgo } from '../../lib/format';
import type { Feed } from '../../lib/types';
import { FeedArtwork } from './FeedArtwork';
import { FilterChipList } from './FilterChipList';

interface AdminFeedCardProps {
  feed: Feed;
  onDelete: (feed: Feed) => Promise<void>;
}

export function AdminFeedCard({ feed, onDelete }: AdminFeedCardProps) {
  const [confirming, setConfirming] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const refreshed = timeAgo(feed.cacheGeneratedAt);
  const editUrl = `/admin/feeds/${feed.id}/edit`;

  const confirmDelete = async () => {
    setDeleting(true);
    try {
      await onDelete(feed);
    } finally {
      setDeleting(false);
      setConfirming(false);
    }
  };

  return (
    <div className="feed-row">
      <FeedArtwork artworkUrl={feed.artworkUrl} />
      <div className="feed-row-main">
        <div className="feed-row-title">
          <h3><Link to={editUrl}>{feed.title}</Link></h3>
          <span className={`status ${refreshed ? 'live' : ''}`}>
            {refreshed ? `Refreshed ${refreshed}` : 'Not fetched yet'}
          </span>
        </div>
        <FilterChipList filters={feed.filters} />
      </div>
      <div className="feed-row-actions">
        {confirming ? (
          <div className="confirm-inline">
            <span>Delete this feed?</span>
            <button type="button" className="btn btn-ghost btn-sm" onClick={() => setConfirming(false)} disabled={deleting}>Cancel</button>
            <button type="button" className="btn btn-danger btn-sm" onClick={confirmDelete} disabled={deleting}>
              {deleting ? 'Deleting…' : 'Delete'}
            </button>
          </div>
        ) : (
          <>
            <CopyButton value={feed.publicUrl} className="btn btn-ghost btn-sm" />
            <a className="btn btn-ghost btn-icon" href={feed.publicUrl} target="_blank" rel="noreferrer" title="Open RSS feed" aria-label="Open RSS feed">
              <Icon name="external" />
            </a>
            <Link className="btn btn-ghost btn-icon" to={editUrl} title="Edit" aria-label={`Edit ${feed.title}`}>
              <Icon name="edit" />
            </Link>
            <button type="button" className="btn btn-ghost btn-icon danger" onClick={() => setConfirming(true)} title="Delete" aria-label={`Delete ${feed.title}`}>
              <Icon name="trash" />
            </button>
          </>
        )}
      </div>
    </div>
  );
}
