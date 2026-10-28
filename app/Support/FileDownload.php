<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\StorageUnavailable;
use App\Models\BookFile;
use App\Services\Catalog\BookFileService;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Streams a book file to the client without loading it in memory.
 */
final class FileDownload
{
    public static function stream(BookFileService $files, BookFile $file): StreamedResponse
    {
        try {
            ['stream' => $stream, 'backend' => $backend] = $files->open($file);
        } catch (StorageUnavailable $e) {
            throw new ServiceUnavailableHttpException(30, 'File temporarily unavailable.', $e);
        }

        return new StreamedResponse(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $file->mime_type,
            'Content-Length' => (string) $file->size,
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $file->original_name,
                self::asciiFallback($file->original_name),
            ),
            'ETag' => '"'.$file->checksum.'"',
            // Which copy answered: visible proof of the fallback in the demo.
            'X-Served-From' => $backend,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private static function asciiFallback(string $name): string
    {
        return preg_replace('/[^\x20-\x7E]|[%\/\\\\"]/', '_', $name) ?? 'download';
    }
}
