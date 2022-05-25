<?php
declare(strict_types=1);

namespace MichalHepner\PhpFileStorage;

use ArrayIterator;
use FilesystemIterator;
use Iterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Filesystem\Filesystem;

class Storage implements StorageInterface
{
    public function __construct(
        protected string $dir,
        protected string $prefix,
        protected string $suffix,
        protected ?Filesystem $filesystem = null,
    ) {
        $this->filesystem = $filesystem ?? new Filesystem();
    }

    public function all(): array
    {
        $this->ensureInitialized();

        $files = [];
        foreach ($this->getRelativePaths() as $relativePath) {
            $files[] = new File(
                $this->dir,
                File::decodeFilename($relativePath, $this->prefix, $this->suffix),
                $this->prefix,
                $this->suffix,
            );
        }

        return $files;
    }

    public function remove(string $filename): void
    {
        $this->ensureInitialized();
        File::encodeFilename($filename, $this->prefix, $this->suffix);
        $absolutePath = $this->getAbsolutePath($filename);

        if (!$this->filesystem->exists($absolutePath)) {
            throw new RuntimeException($this, sprintf('File %s does not exists in storage', $filename));
        }

        $this->filesystem->remove($absolutePath);
    }

    public function count(): int
    {
        $this->ensureInitialized();

        return count($this->getRelativePaths());
    }

    public function getIterator(): Iterator
    {
        return new ArrayIterator($this->all());
    }

    public function clear(): void
    {
        $this->ensureInitialized();

        /** @var File $file */
        foreach ($this->all() as $file) {
            $file->remove();
        }
    }

    protected function getRelativePaths(): array
    {
        $this->ensureInitialized();

        $directory = new RecursiveDirectoryIterator($this->dir, FilesystemIterator::FOLLOW_SYMLINKS);
        $filter = new RecursiveCallbackFilterIterator($directory, function (SplFileInfo $current) {
            if ($current->isDir() || $current->isFile()) {
                $relativePath = rtrim($this->filesystem->makePathRelative($current->getPathname(), $this->dir), DIRECTORY_SEPARATOR);
                if (!str_starts_with($relativePath, $this->prefix) || str_ends_with(dirname($relativePath), $this->suffix)) {
                    return false;
                }

                return true;
            }

            return false;
        });

        $iterator = new RecursiveIteratorIterator($filter);
        $files = [];
        foreach ($iterator as $info) {
            $relativePath = $this->filesystem->makePathRelative($info->getPathname(), $this->dir);
            $relativePath = rtrim($relativePath, '.'.DIRECTORY_SEPARATOR);
            if (str_ends_with($relativePath, $this->suffix)) {
                $files[] = $relativePath;
            }
        }

        return $files;
    }

    protected function ensureInitialized(): void
    {
        if (!$this->filesystem->exists($this->dir)) {
            $this->filesystem->mkdir($this->dir);
        }
    }

    protected function getRelativePath(string $filename): string
    {
        return File::encodeFilename($filename, $this->prefix, $this->suffix);
    }

    protected function getAbsolutePath(string $filename): string
    {
        return rtrim($this->dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->getRelativePath($filename);
    }

    public function exists(string $filename): bool
    {
        $this->ensureInitialized();

        return $this->filesystem->exists($this->getAbsolutePath($filename));
    }

    public function touch(string $filename): File
    {
        $this->ensureInitialized();

        $absPath = $this->getAbsolutePath($filename);
        $parentDir = dirname($absPath);
        if (!$this->filesystem->exists($parentDir)) {
            $this->filesystem->mkdir($parentDir);
        }

        $this->filesystem->touch($absPath);

        return new File($this->dir, $filename, $this->prefix, $this->suffix);
    }

    public function dump(string $filename, string $contents): File
    {
        $file = $this->touch($filename);
        file_put_contents($file->getPathname(), $contents);

        return $file;
    }

    public function mkdir(string $filename): File
    {
        $this->ensureInitialized();

        $this->filesystem->mkdir($this->getAbsolutePath($filename));

        return new File($this->dir, $filename, $this->prefix, $this->suffix);
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    public function get(string $filename): File
    {
        $this->ensureInitialized();

        if (!$this->filesystem->exists($this->getAbsolutePath($filename))) {
            throw new RuntimeException(sprintf('File %s does not exist in storage', $filename));
        }

        return new File($this->dir, $filename, $this->prefix, $this->suffix);
    }
}
