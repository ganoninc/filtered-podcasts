<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\RedirectionException;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PodcastDirectorySearchService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(service: 'cache.app')]
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @return list<array{title: string, author: string, feedUrl: string, artworkUrl: ?string, appleUrl: ?string}>
     */
    public function search(string $query, int $limit = 12): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $cacheKey = 'podcast_search_'.sha1(mb_strtolower($query).'_'.$limit);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($query, $limit): array {
            $item->expiresAfter(21600);

            try {
                $response = $this->httpClient->request('GET', 'https://itunes.apple.com/search', [
                    'query' => [
                        'term' => $query,
                        'media' => 'podcast',
                        'entity' => 'podcast',
                        'limit' => min(max($limit, 1), 20),
                    ],
                    'headers' => [
                        'Accept' => 'application/json',
                        'User-Agent' => 'FilteredPodcastsBot/1.0',
                    ],
                    'timeout' => 15,
                ]);

                $payload = $response->toArray();
            } catch (TransportException|ClientException|RedirectionException|ServerException $exception) {
                throw new \RuntimeException(sprintf('Podcast search is unavailable: %s', $exception->getMessage()), previous: $exception);
            }

            $results = [];

            foreach (($payload['results'] ?? []) as $row) {
                $feedUrl = isset($row['feedUrl']) && is_string($row['feedUrl']) ? trim($row['feedUrl']) : '';
                if ($feedUrl === '') {
                    continue;
                }

                $results[] = [
                    'title' => isset($row['collectionName']) && is_string($row['collectionName']) ? $row['collectionName'] : 'Untitled podcast',
                    'author' => isset($row['artistName']) && is_string($row['artistName']) ? $row['artistName'] : 'Unknown author',
                    'feedUrl' => $feedUrl,
                    'artworkUrl' => isset($row['artworkUrl100']) && is_string($row['artworkUrl100']) ? $row['artworkUrl100'] : null,
                    'appleUrl' => isset($row['collectionViewUrl']) && is_string($row['collectionViewUrl']) ? $row['collectionViewUrl'] : null,
                ];
            }

            return $results;
        });
    }
}
