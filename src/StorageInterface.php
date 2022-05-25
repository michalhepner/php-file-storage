<?php
declare(strict_types=1);

namespace MichalHepner\PhpFileStorage;

use Countable;
use IteratorAggregate;

interface StorageInterface extends IteratorAggregate, Countable
{
    public function get(string $filename): File;
    public function remove(String $filename): void;
    public function exists(string $filename): bool;
    public function touch(string $filename): File;
    public function dump(string $filename, string $contents): File;
    public function mkdir(string $filename): File;
    public function isEmpty(): bool;

    /**
     * @return File[]
     */
    public function all(): array;
}
