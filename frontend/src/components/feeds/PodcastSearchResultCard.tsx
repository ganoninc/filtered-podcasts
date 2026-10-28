import type { DirectoryResult } from '../../lib/types';
import { FeedArtwork } from './FeedArtwork';

interface PodcastSearchResultCardProps {
  result: DirectoryResult;
  onUse: (result: DirectoryResult) => void;
}

export function PodcastSearchResultCard({ result, onUse }: PodcastSearchResultCardProps) {
  return (
    <div className="result">
      <FeedArtwork artworkUrl={result.artworkUrl} size="sm" />
      <div className="result-main">
        <span className="result-title truncate">{result.title}</span>
        <span className="subtle truncate">{result.author}</span>
      </div>
      <button type="button" className="btn btn-secondary btn-sm" onClick={() => onUse(result)}>Select</button>
    </div>
  );
}
