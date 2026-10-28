import { useState } from 'react';
import { Icon } from '../ui/Icon';

interface FeedArtworkProps {
  artworkUrl: string | null;
  size?: 'sm' | 'md' | 'lg';
}

export function FeedArtwork({ artworkUrl, size = 'md' }: FeedArtworkProps) {
  const [failed, setFailed] = useState(false);
  const sizeClass = size === 'md' ? '' : size;

  if (artworkUrl && !failed) {
    return <img className={`artwork ${sizeClass}`} src={artworkUrl} alt="" loading="lazy" onError={() => setFailed(true)} />;
  }

  return (
    <div className={`artwork artwork-fallback ${sizeClass}`} aria-hidden="true">
      <Icon name="podcast" size={size === 'sm' ? 18 : 22} />
    </div>
  );
}
