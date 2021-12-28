<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Documentation;

final class Tag
{
    /**
     * @var array<string, string>|null
     * @readonly
     */
    private ?array $externalDocs;

    public function __construct(
        private string $name,
        private ?string $description = null,
        ?string $url = null
    ) {
        $this->externalDocs = $url
            ? ['url' => $url]
            : null;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return array<string, string>|null
     */
    public function getExternalDocs(): ?array
    {
        return $this->externalDocs;
    }

    public function withName(string $name): self
    {
        $clone = clone $this;
        $clone->name = $name;

        return $clone;
    }

    public function withDescription(string $description): self
    {
        $clone = clone $this;
        $clone->description = $description;

        return $clone;
    }

    public function withUrl(string $url): self
    {
        $clone = clone $this;
        $clone->externalDocs = ['url' => $url];

        return $clone;
    }
}
