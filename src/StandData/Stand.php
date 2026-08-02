<?php

namespace VatsimData\StandData;

final class Stand
{
    public ?Aircraft $occupier = null;

    /** @param string[] $extensions */
    public function __construct(
        public readonly string $id,
        public readonly float $latitude,
        public readonly float $longitude,
        private readonly array $extensions = ['L', 'C', 'R', 'A', 'B', 'N', 'E', 'S', 'W'],
        private readonly string $pattern = '<standroot><extensions>',
    ) {
        if ($this->id === '') {
            throw new \InvalidArgumentException('A stand identifier is required.');
        }
    }

    public function isOccupied(): bool
    {
        return $this->occupier !== null;
    }

    public function getName(): string
    {
        return $this->id;
    }

    public function getKey(): string
    {
        return $this->id;
    }

    public function getRoot(): ?string
    {
        $matches = $this->matches();

        if ($matches === null) {
            return null;
        }

        return $this->standRootComesFirst() ? $matches[1] : $matches[2];
    }

    public function getExtension(): ?string
    {
        $matches = $this->matches();

        if ($matches === null) {
            return null;
        }

        $extension = $this->standRootComesFirst() ? ($matches[2] ?? null) : $matches[1];

        return $extension !== '' ? $extension : null;
    }

    public function clear(): void
    {
        $this->occupier = null;
    }

    /** @return array<int, string>|null */
    private function matches(): ?array
    {
        $extensions = implode('|', array_map(static fn (string $extension): string => preg_quote($extension, '/'), $this->extensions));
        $expression = str_replace(['<standroot>', '<extensions>'], ['([0-9]+)', '('.$extensions.')?'], $this->pattern);

        return preg_match('/^'.$expression.'$/', $this->id, $matches) === 1 ? $matches : null;
    }

    private function standRootComesFirst(): bool
    {
        return strpos($this->pattern, '<standroot>') < strpos($this->pattern, '<extensions>');
    }
}
