<?php

namespace App\Services\Node;

use App\Models\Cell;

class FileArchiveNodeClient
{
    public function __construct(private NodeClient $nodeClient) {
    }

    public function createArchive(
        Cell $cell,
        array $paths,
        string $destination,
        string $format,
    ): array {
        return $this->nodeClient
            ->client($cell->node)
            ->post(
                '/cells/'
                . rawurlencode($cell->daemon_id)
                . '/files/archive',
                [
                    'paths' => array_values($paths),
                    'destination' => $destination,
                    'format' => $format,
                ]
            )
            ->throw()
            ->json();
    }

    public function extractArchive(
        Cell $cell,
        string $path,
        string $destination,
        bool $overwrite,
    ): array {
        return $this->nodeClient
            ->client($cell->node)
            ->post(
                '/cells/'
                . rawurlencode($cell->daemon_id)
                . '/files/extract',
                [
                    'path' => $path,
                    'destination' => $destination,
                    'overwrite' => $overwrite,
                ]
            )
            ->throw()
            ->json();
    }

    public function rename(
        Cell $cell,
        string $oldPath,
        string $newPath,
    ): array {
        return $this->nodeClient
            ->client($cell->node)
            ->post(
                '/cells/'
                . rawurlencode($cell->daemon_id)
                . '/files/rename',
                [
                    'old_path' => $oldPath,
                    'new_path' => $newPath,
                ]
            )
            ->throw()
            ->json();
    }
}