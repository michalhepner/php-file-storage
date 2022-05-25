<?php
declare(strict_types=1);

namespace MichalHepner\PhpFileStorage;

use Base32\Base32;
use SplFileInfo;
use Symfony\Component\Filesystem\Filesystem;

class File extends SplFileInfo
{
    public function __construct(
        protected string $storagePath,
        protected string $decodedFilename,
        protected string $prefix,
        protected string $suffix,
    ) {
        parent::__construct(implode(DIRECTORY_SEPARATOR, [
            $this->storagePath,
            self::encodeFilename($this->decodedFilename, $this->prefix, $this->suffix)
        ]));
    }

    public function getDecodedFilename(): string
    {
        return $this->decodedFilename;
    }

    public static function encodeFilename(string $filename, string $prefix, string $suffix): string
    {
        return $prefix . implode(DIRECTORY_SEPARATOR, str_split(Base32::encode($filename), 32)) . $suffix;
    }

    public static function decodeFilename(string $encodedFilename, string $prefix, string $suffix): string
    {
        if (!str_starts_with($encodedFilename, $prefix) || !str_ends_with($encodedFilename, $suffix)) {
            throw new \Exception(sprintf(
                'Failed to decode name \'%s\', it does not use the defined prefix \'%s\' or suffix \'%s\'',
                $encodedFilename,
                $prefix,
                $suffix
            ));
        }

        return Base32::decode(
            preg_replace('/^' . preg_quote($prefix, '/') . '/', '',
                preg_replace('/' . preg_quote($suffix, '$/') . '/', '',
                    str_replace(DIRECTORY_SEPARATOR, '', $encodedFilename)
                )
            )
        );
    }

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function getSuffix(): string
    {
        return $this->suffix;
    }

    public function remove(): void
    {
        (new Filesystem())->remove($this->getPathname());
    }
}
