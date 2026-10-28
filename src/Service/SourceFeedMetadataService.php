<?php

namespace App\Service;

use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\RedirectionException;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SourceFeedMetadataService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function resolveArtworkUrl(string $sourceUrl): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $sourceUrl, [
                'timeout' => 15,
                'max_redirects' => 5,
                'headers' => [
                    'User-Agent' => 'FilteredPodcastsBot/1.0',
                    'Accept' => 'application/rss+xml, application/xml, text/xml;q=0.9, */*;q=0.8',
                ],
            ]);

            $xml = $response->getContent();
        } catch (TransportException|ClientException|RedirectionException|ServerException) {
            return null;
        }

        $previousState = libxml_use_internal_errors(true);

        $document = new \DOMDocument('1.0', 'UTF-8');
        if (!$document->loadXML($xml, LIBXML_NOCDATA | LIBXML_NOBLANKS)) {
            libxml_clear_errors();
            libxml_use_internal_errors($previousState);

            return null;
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previousState);

        $xpath = new \DOMXPath($document);
        foreach ($document->documentElement?->attributes ?? [] as $attribute) {
            if ($attribute instanceof \DOMAttr && str_starts_with($attribute->nodeName, 'xmlns:')) {
                $xpath->registerNamespace(substr($attribute->nodeName, 6), $attribute->nodeValue);
            }
        }

        $queries = [
            'string(/*[local-name()="rss"]/*[local-name()="channel"]/*[local-name()="image"]/*[local-name()="url"][1])',
            'string(/*[local-name()="rss"]/*[local-name()="channel"]/*[local-name()="image" and namespace-uri()="http://www.itunes.com/dtds/podcast-1.0.dtd"][1]/@href)',
            'string(/*[local-name()="feed"]/*[local-name()="icon"][1])',
            'string(/*[local-name()="feed"]/*[local-name()="logo"][1])',
        ];

        foreach ($queries as $query) {
            $value = trim((string) $xpath->evaluate($query));
            if ($value !== '' && filter_var($value, FILTER_VALIDATE_URL) !== false) {
                return $value;
            }
        }

        return null;
    }
}
