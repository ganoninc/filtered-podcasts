<?php

namespace App\Service;

use App\Entity\PodcastFeed;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\RedirectionException;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FeedFilterService
{
    private const CACHE_TTL_SECONDS = 86400;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getFilteredXml(PodcastFeed $feed, string $selfUrl): string
    {
        $generatedAt = $feed->getCacheGeneratedAt();
        if ($feed->getCachedXml() !== null && $generatedAt !== null) {
            if ((new \DateTimeImmutable())->getTimestamp() - $generatedAt->getTimestamp() < self::CACHE_TTL_SECONDS) {
                return $feed->getCachedXml();
            }
        }

        $sourceXml = $this->download($feed->getSourceUrl());
        $filteredXml = $this->filterFeedXml($sourceXml, $feed, $selfUrl);

        $feed
            ->setCachedXml($filteredXml)
            ->setCacheGeneratedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return $filteredXml;
    }

    private function download(string $url): string
    {
        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 20,
                'max_redirects' => 5,
                'headers' => [
                    'User-Agent' => 'FilteredPodcastsBot/1.0',
                    'Accept' => 'application/rss+xml, application/xml, text/xml;q=0.9, */*;q=0.8',
                ],
            ]);

            return $response->getContent();
        } catch (TransportException|ClientException|RedirectionException|ServerException $exception) {
            throw new \RuntimeException(sprintf('Unable to fetch source feed: %s', $exception->getMessage()), previous: $exception);
        }
    }

    private function filterFeedXml(string $sourceXml, PodcastFeed $feed, string $selfUrl): string
    {
        $previousState = libxml_use_internal_errors(true);

        $document = new \DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = false;
        $document->formatOutput = true;

        if (!$document->loadXML($sourceXml, LIBXML_NOCDATA | LIBXML_NOBLANKS)) {
            $message = 'Invalid source XML.';
            $errors = libxml_get_errors();
            if ($errors !== []) {
                $message = trim($errors[0]->message);
            }

            libxml_clear_errors();
            libxml_use_internal_errors($previousState);

            throw new \RuntimeException(sprintf('Unable to parse source feed XML: %s', $message));
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previousState);

        $xpath = new \DOMXPath($document);
        foreach ($document->documentElement?->attributes ?? [] as $attribute) {
            if ($attribute instanceof \DOMAttr && str_starts_with($attribute->nodeName, 'xmlns:')) {
                $xpath->registerNamespace(substr($attribute->nodeName, 6), $attribute->nodeValue);
            }
        }

        $channel = $xpath->query('/*[local-name()="rss"]/*[local-name()="channel"]')->item(0);
        if (!$channel instanceof \DOMElement) {
            throw new \RuntimeException('Only RSS feeds with a channel element are supported.');
        }

        foreach (iterator_to_array($channel->childNodes) as $childNode) {
            if (!$childNode instanceof \DOMElement || $childNode->localName !== 'item') {
                continue;
            }

            if (!$this->itemMatches($xpath, $childNode, $feed)) {
                $channel->removeChild($childNode);
            }
        }

        $this->upsertSimpleElement($document, $channel, 'title', $feed->getTitle());

        $description = $feed->getDescription() ?: sprintf('Filtered podcast feed based on %s.', $feed->getSourceUrl());
        $this->upsertSimpleElement($document, $channel, 'description', $description);

        $atomLink = $xpath->query('./*[local-name()="link" and namespace-uri()="http://www.w3.org/2005/Atom"][@rel="self"]', $channel)->item(0);
        if ($atomLink instanceof \DOMElement) {
            $atomLink->setAttribute('href', $selfUrl);
            $atomLink->setAttribute('type', 'application/rss+xml');
        }

        // Podcast apps follow new-feed-url and would move subscribers back to the unfiltered source.
        foreach (iterator_to_array($xpath->query('./*[local-name()="new-feed-url"]', $channel)) as $newFeedUrl) {
            $channel->removeChild($newFeedUrl);
        }

        return $document->saveXML() ?: throw new \RuntimeException('Unable to build filtered feed XML.');
    }

    private function itemMatches(\DOMXPath $xpath, \DOMElement $item, PodcastFeed $feed): bool
    {
        $title = trim($xpath->evaluate('string(./*[local-name()="title"][1])', $item));
        $durationSeconds = $this->extractDurationSeconds($xpath, $item);
        $filtersByCategory = [];

        foreach ($feed->getFilters() as $filter) {
            $category = $filter['category'] ?? null;
            if (!is_string($category)) {
                continue;
            }

            $filtersByCategory[$category][] = $filter;
        }

        foreach ($filtersByCategory as $categoryFilters) {
            if (!$this->evaluateCategoryFilters($categoryFilters, $title, $durationSeconds)) {
                return false;
            }
        }

        return true;
    }

    private function evaluateCategoryFilters(array $filters, string $title, ?int $durationSeconds): bool
    {
        $result = null;

        foreach ($filters as $index => $filter) {
            $current = $this->evaluateSingleFilter($filter, $title, $durationSeconds);

            if ($index === 0 || $result === null) {
                $result = $current;

                continue;
            }

            $combinator = $filter['combinator'] ?? PodcastFeed::COMBINATOR_AND;
            $result = $combinator === PodcastFeed::COMBINATOR_OR
                ? ($result || $current)
                : ($result && $current);
        }

        return $result ?? true;
    }

    private function evaluateSingleFilter(array $filter, string $title, ?int $durationSeconds): bool
    {
        $operator = $filter['operator'] ?? null;
        $value = $filter['value'] ?? null;

        return match ($operator) {
            PodcastFeed::OPERATOR_CONTAINS => is_string($value) && str_contains(mb_strtolower($title), mb_strtolower($value)),
            PodcastFeed::OPERATOR_NOT_CONTAINS => is_string($value) && !str_contains(mb_strtolower($title), mb_strtolower($value)),
            PodcastFeed::OPERATOR_SHORTER_THAN => is_int($value) && $durationSeconds !== null && $durationSeconds < ($value * 60),
            PodcastFeed::OPERATOR_LONGER_THAN => is_int($value) && $durationSeconds !== null && $durationSeconds > ($value * 60),
            default => true,
        };
    }

    private function extractDurationSeconds(\DOMXPath $xpath, \DOMElement $item): ?int
    {
        $rawDuration = trim($xpath->evaluate('string(./*[local-name()="duration"][1])', $item));
        if ($rawDuration === '') {
            return null;
        }

        if (ctype_digit($rawDuration)) {
            return (int) $rawDuration;
        }

        $parts = array_map('intval', explode(':', $rawDuration));

        return match (count($parts)) {
            2 => ($parts[0] * 60) + $parts[1],
            3 => ($parts[0] * 3600) + ($parts[1] * 60) + $parts[2],
            default => null,
        };
    }

    private function upsertSimpleElement(\DOMDocument $document, \DOMElement $channel, string $name, string $value): void
    {
        foreach ($channel->childNodes as $childNode) {
            if ($childNode instanceof \DOMElement && $childNode->tagName === $name) {
                $childNode->textContent = $value;

                return;
            }
        }

        $element = $document->createElement($name);
        $element->textContent = $value;
        $channel->insertBefore($element, $channel->firstChild);
    }
}
