<?php

namespace App\Controller;

use App\Entity\PodcastFeed;
use App\Entity\User;
use App\Repository\PodcastFeedRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route('/api')]
class ApiController extends AbstractController
{
    public function __construct(
        private readonly PodcastFeedRepository $feedRepository,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('/bootstrap', name: 'api_bootstrap', methods: ['GET'])]
    public function bootstrap(AuthenticationUtils $authenticationUtils): JsonResponse
    {
        $user = $this->getUser();
        $error = $authenticationUtils->getLastAuthenticationError();

        return $this->json([
            'user' => $user instanceof User ? [
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ] : null,
            'csrf' => [
                'login' => $this->csrfTokenManager->getToken('authenticate')->getValue(),
                'logout' => $this->csrfTokenManager->getToken('logout')->getValue(),
                'adminApi' => $this->csrfTokenManager->getToken('admin-api')->getValue(),
            ],
            'auth' => [
                'lastUsername' => $authenticationUtils->getLastUsername(),
                'lastError' => $error?->getMessageKey(),
            ],
        ]);
    }

    #[Route('/public/feeds', name: 'api_public_feeds', methods: ['GET'])]
    public function publicFeeds(): JsonResponse
    {
        return $this->json([
            'feeds' => array_map(fn (PodcastFeed $feed) => [
                'id' => $feed->getId(),
                'title' => $feed->getTitle(),
                'slug' => $feed->getSlug(),
                'description' => $feed->getDescription(),
                'artworkUrl' => $feed->getArtworkUrl(),
                'sourceUrl' => $feed->getSourceUrl(),
                'filters' => $feed->getFilters(),
                'publicUrl' => $this->generateUrl('public_filtered_feed', [
                    'id' => $feed->getId(),
                    'slug' => $feed->getSlug(),
                ], 0),
            ], $this->feedRepository->findAllOrdered()),
        ]);
    }
}
