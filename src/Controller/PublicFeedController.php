<?php

namespace App\Controller;

use App\Entity\PodcastFeed;
use App\Service\FeedFilterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PublicFeedController extends AbstractController
{
    #[Route('/filtered/{id}-{slug}', name: 'public_filtered_feed', methods: ['GET'])]
    public function show(PodcastFeed $feed, string $slug, FeedFilterService $feedFilterService): Response
    {
        if ($slug !== $feed->getSlug()) {
            return $this->redirectToRoute('public_filtered_feed', [
                'id' => $feed->getId(),
                'slug' => $feed->getSlug(),
            ], Response::HTTP_MOVED_PERMANENTLY);
        }

        $xml = $feedFilterService->getFilteredXml(
            $feed,
            $this->generateUrl('public_filtered_feed', [
                'id' => $feed->getId(),
                'slug' => $feed->getSlug(),
            ], UrlGeneratorInterface::ABSOLUTE_URL)
        );

        return new Response($xml, Response::HTTP_OK, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
        ]);
    }
}
