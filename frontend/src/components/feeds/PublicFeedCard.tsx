import { CopyButton } from '../ui/CopyButton';
import { Icon } from '../ui/Icon';
import { hostOf, podcastAppUrl } from '../../lib/format';
import type { PublicFeed } from '../../lib/types';
import { FeedArtwork } from './FeedArtwork';
import { FilterChipList } from './FilterChipList';

interface PublicFeedCardProps {
  feed: PublicFeed;
}

export function PublicFeedCard({ feed }: PublicFeedCardProps) {
  return (
    <article className="card feed-card">
      <div className="feed-card-head">
        <FeedArtwork artworkUrl={feed.artworkUrl} size="lg" />
        <div className="feed-card-body">
          <h3 className="feed-card-title">{feed.title}</h3>
          {feed.description ? <p className="feed-card-desc">{feed.description}</p> : null}
          <span className="subtle truncate">
            Based on <a href={feed.sourceUrl} target="_blank" rel="noreferrer">{hostOf(feed.sourceUrl)}</a>
          </span>
        </div>
      </div>
      <FilterChipList filters={feed.filters} />
      <div className="feed-card-actions">
        <a className="btn btn-primary" href={podcastAppUrl(feed.publicUrl)}>
          <Icon name="podcast" />
          Subscribe
        </a>
        <CopyButton value={feed.publicUrl} label="Copy RSS link" />
      </div>
    </article>
  );
}
