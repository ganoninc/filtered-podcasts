<?php

namespace App\Entity;

use App\Repository\PodcastFeedRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PodcastFeedRepository::class)]
#[ORM\HasLifecycleCallbacks]
class PodcastFeed
{
    public const CATEGORY_TITLE = 'title';
    public const CATEGORY_DURATION = 'duration';

    public const OPERATOR_CONTAINS = 'contains';
    public const OPERATOR_NOT_CONTAINS = 'not_contains';
    public const OPERATOR_SHORTER_THAN = 'shorter_than';
    public const OPERATOR_LONGER_THAN = 'longer_than';

    public const COMBINATOR_AND = 'and';
    public const COMBINATOR_OR = 'or';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(length: 255)]
    private string $slug = '';

    #[ORM\Column(length: 2048)]
    private string $sourceUrl = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $artworkUrl = null;

    #[ORM\Column(type: 'json')]
    private array $filters = [];

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $cachedXml = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cacheGeneratedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = trim($title);

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getSourceUrl(): string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(string $sourceUrl): self
    {
        $this->sourceUrl = trim($sourceUrl);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description !== null ? trim($description) : null;

        return $this;
    }

    public function getArtworkUrl(): ?string
    {
        return $this->artworkUrl;
    }

    public function setArtworkUrl(?string $artworkUrl): self
    {
        $this->artworkUrl = $this->normalizeNullableString($artworkUrl);

        return $this;
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function setFilters(array $filters): self
    {
        $normalized = [];

        foreach ($filters as $filter) {
            if (!is_array($filter)) {
                continue;
            }

            $category = isset($filter['category']) ? trim((string) $filter['category']) : '';
            $operator = isset($filter['operator']) ? trim((string) $filter['operator']) : '';
            $combinator = isset($filter['combinator']) ? trim((string) $filter['combinator']) : self::COMBINATOR_AND;
            $value = $filter['value'] ?? null;

            if ($category === '' || $operator === '') {
                continue;
            }

            if ($category === self::CATEGORY_TITLE) {
                $value = $this->normalizeNullableString(is_scalar($value) ? (string) $value : null);
            } elseif ($category === self::CATEGORY_DURATION) {
                $value = is_numeric($value) ? (int) $value : null;
            } else {
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            $normalized[] = [
                'category' => $category,
                'operator' => $operator,
                'value' => $value,
                'combinator' => $combinator === self::COMBINATOR_OR ? self::COMBINATOR_OR : self::COMBINATOR_AND,
            ];
        }

        $this->filters = array_values($normalized);

        return $this;
    }

    public function getCachedXml(): ?string
    {
        return $this->cachedXml;
    }

    public function setCachedXml(?string $cachedXml): self
    {
        $this->cachedXml = $cachedXml;

        return $this;
    }

    public function getCacheGeneratedAt(): ?\DateTimeImmutable
    {
        return $this->cacheGeneratedAt;
    }

    public function setCacheGeneratedAt(?\DateTimeImmutable $cacheGeneratedAt): self
    {
        $this->cacheGeneratedAt = $cacheGeneratedAt;

        return $this;
    }

    public function clearCache(): self
    {
        $this->cachedXml = null;
        $this->cacheGeneratedAt = null;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    private function normalizeNullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    public static function categoryChoices(): array
    {
        return [
            'Title' => self::CATEGORY_TITLE,
            'Duration' => self::CATEGORY_DURATION,
        ];
    }

    public static function operatorChoices(): array
    {
        return [
            'Contains' => self::OPERATOR_CONTAINS,
            'Does not contain' => self::OPERATOR_NOT_CONTAINS,
            'Is shorter than (minutes)' => self::OPERATOR_SHORTER_THAN,
            'Is longer than (minutes)' => self::OPERATOR_LONGER_THAN,
        ];
    }

    public static function operatorChoicesByCategory(): array
    {
        return [
            self::CATEGORY_TITLE => [
                'Contains' => self::OPERATOR_CONTAINS,
                'Does not contain' => self::OPERATOR_NOT_CONTAINS,
            ],
            self::CATEGORY_DURATION => [
                'Is shorter than (minutes)' => self::OPERATOR_SHORTER_THAN,
                'Is longer than (minutes)' => self::OPERATOR_LONGER_THAN,
            ],
        ];
    }

    public static function combinatorChoices(): array
    {
        return [
            'AND' => self::COMBINATOR_AND,
            'OR' => self::COMBINATOR_OR,
        ];
    }
}
