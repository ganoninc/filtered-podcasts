<?php

namespace App\Controller\Admin;

use App\Entity\PodcastFeed;
use App\Repository\PodcastFeedRepository;
use App\Service\PodcastDirectorySearchService;
use App\Service\SourceFeedMetadataService;
use App\Service\Slugger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/api/admin/feeds')]
class PodcastFeedController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PodcastFeedRepository $feedRepository,
        private readonly Slugger $slugger,
        private readonly SourceFeedMetadataService $sourceFeedMetadataService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('', name: 'api_admin_feed_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'feeds' => array_map(fn (PodcastFeed $feed) => $this->serializeFeed($feed), $this->feedRepository->findAllOrdered()),
        ]);
    }

    #[Route('/{id}', name: 'api_admin_feed_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(PodcastFeed $feed): JsonResponse
    {
        return $this->json([
            'feed' => $this->serializeFeed($feed),
        ]);
    }

    #[Route('', name: 'api_admin_feed_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        if (!$this->isValidMutationToken($request)) {
            return $this->json(['message' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        $payload = $this->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $validation = $this->validatePayload($payload);
        if ($validation['errors'] !== []) {
            return $this->json(['errors' => $validation['errors']], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $feed = (new PodcastFeed())
            ->setTitle($validation['data']['title'])
            ->setSlug($this->slugger->slugify($validation['data']['title']))
            ->setSourceUrl($validation['data']['sourceUrl'])
            ->setDescription($validation['data']['description'])
            ->setArtworkUrl($this->sourceFeedMetadataService->resolveArtworkUrl($validation['data']['sourceUrl']))
            ->setFilters($validation['data']['filters']);

        $this->entityManager->persist($feed);
        $this->entityManager->flush();

        return $this->json([
            'feed' => $this->serializeFeed($feed),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_admin_feed_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(PodcastFeed $feed, Request $request): JsonResponse
    {
        if (!$this->isValidMutationToken($request)) {
            return $this->json(['message' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        $payload = $this->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $validation = $this->validatePayload($payload);
        if ($validation['errors'] !== []) {
            return $this->json(['errors' => $validation['errors']], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $feed
            ->setTitle($validation['data']['title'])
            ->setSlug($this->slugger->slugify($validation['data']['title']))
            ->setSourceUrl($validation['data']['sourceUrl'])
            ->setDescription($validation['data']['description'])
            ->setArtworkUrl($this->sourceFeedMetadataService->resolveArtworkUrl($validation['data']['sourceUrl']) ?? $feed->getArtworkUrl())
            ->setFilters($validation['data']['filters'])
            ->clearCache();

        $this->entityManager->flush();

        return $this->json([
            'feed' => $this->serializeFeed($feed),
        ]);
    }

    #[Route('/{id}', name: 'api_admin_feed_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(PodcastFeed $feed, Request $request): JsonResponse
    {
        if (!$this->isValidMutationToken($request)) {
            return $this->json(['message' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        $this->entityManager->remove($feed);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/search/source', name: 'api_admin_feed_source_search', methods: ['GET'])]
    public function searchSource(Request $request, PodcastDirectorySearchService $searchService): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));
        if ($query === '') {
            return $this->json(['results' => []]);
        }

        try {
            return $this->json([
                'results' => $searchService->search($query),
            ]);
        } catch (\RuntimeException $exception) {
            return $this->json([
                'error' => $exception->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }
    }

    private function decodeJson(Request $request): array|JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\JsonException) {
            return $this->json(['message' => 'Invalid JSON payload.'], Response::HTTP_BAD_REQUEST);
        }

        return is_array($payload) ? $payload : [];
    }

    /**
     * @return array{data: array{title: string, sourceUrl: string, description: ?string, filters: array}, errors: array<string, string>}
     */
    private function validatePayload(array $payload): array
    {
        $errors = [];

        $title = trim((string) ($payload['title'] ?? ''));
        $sourceUrl = trim((string) ($payload['sourceUrl'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        $description = $description === '' ? null : $description;
        $filters = is_array($payload['filters'] ?? null) ? $payload['filters'] : [];

        if ($title === '') {
            $errors['title'] = 'Title is required.';
        }

        if ($sourceUrl === '' || filter_var($sourceUrl, FILTER_VALIDATE_URL) === false) {
            $errors['sourceUrl'] = 'A valid source RSS URL is required.';
        }

        $normalizedFilters = [];
        foreach ($filters as $index => $filter) {
            if (!is_array($filter)) {
                $errors['filters.'.$index] = 'Invalid filter.';

                continue;
            }

            $category = (string) ($filter['category'] ?? '');
            $operator = (string) ($filter['operator'] ?? '');
            $combinator = (string) ($filter['combinator'] ?? PodcastFeed::COMBINATOR_AND);
            $value = $filter['value'] ?? null;

            if (!in_array($category, [PodcastFeed::CATEGORY_TITLE, PodcastFeed::CATEGORY_DURATION], true)) {
                $errors['filters.'.$index.'.category'] = 'Invalid category.';
                continue;
            }

            if (!in_array($combinator, [PodcastFeed::COMBINATOR_AND, PodcastFeed::COMBINATOR_OR], true)) {
                $errors['filters.'.$index.'.combinator'] = 'Invalid combinator.';
            }

            if ($category === PodcastFeed::CATEGORY_TITLE) {
                if (!in_array($operator, [PodcastFeed::OPERATOR_CONTAINS, PodcastFeed::OPERATOR_NOT_CONTAINS], true)) {
                    $errors['filters.'.$index.'.operator'] = 'Title filters only support contains and does not contain.';
                }

                $textValue = trim((string) $value);
                if ($textValue === '') {
                    $errors['filters.'.$index.'.value'] = 'Title filters require text.';
                }

                $normalizedFilters[] = [
                    'category' => $category,
                    'operator' => $operator,
                    'value' => $textValue,
                    'combinator' => $combinator,
                ];

                continue;
            }

            if (!in_array($operator, [PodcastFeed::OPERATOR_SHORTER_THAN, PodcastFeed::OPERATOR_LONGER_THAN], true)) {
                $errors['filters.'.$index.'.operator'] = 'Duration filters only support shorter than and longer than.';
            }

            if (!is_numeric($value) || (int) $value < 0) {
                $errors['filters.'.$index.'.value'] = 'Duration filters require a non-negative number of minutes.';
            }

            $normalizedFilters[] = [
                'category' => $category,
                'operator' => $operator,
                'value' => is_numeric($value) ? (int) $value : 0,
                'combinator' => $combinator,
            ];
        }

        return [
            'data' => [
                'title' => $title,
                'sourceUrl' => $sourceUrl,
                'description' => $description,
                'filters' => $normalizedFilters,
            ],
            'errors' => $errors,
        ];
    }

    private function serializeFeed(PodcastFeed $feed): array
    {
        return [
            'id' => $feed->getId(),
            'title' => $feed->getTitle(),
            'slug' => $feed->getSlug(),
            'sourceUrl' => $feed->getSourceUrl(),
            'description' => $feed->getDescription(),
            'artworkUrl' => $feed->getArtworkUrl(),
            'filters' => $feed->getFilters(),
            'cacheGeneratedAt' => $feed->getCacheGeneratedAt()?->format(\DateTimeInterface::ATOM),
            'publicUrl' => $this->generateUrl('public_filtered_feed', [
                'id' => $feed->getId(),
                'slug' => $feed->getSlug(),
            ], 0),
        ];
    }

    private function isValidMutationToken(Request $request): bool
    {
        $token = (string) $request->headers->get('X-CSRF-Token', '');

        return $this->csrfTokenManager->isTokenValid(new CsrfToken('admin-api', $token));
    }
}
