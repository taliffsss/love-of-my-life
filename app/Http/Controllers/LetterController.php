<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\LetterStore;
use App\Services\PrivateWriter;
use App\Services\UploadGate;
use Naluz\Http\HttpException;
use Naluz\Http\Request;
use Naluz\Http\Response;
use Naluz\Storage\StorageException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class LetterController
{
    public function update(ServerRequestInterface $request, UploadGate $gate, LetterStore $letters): ResponseInterface
    {
        $gate->assertAllowed($request);

        $body = Request::body($request);
        try {
            $new = LetterStore::parse((string) ($body['message'] ?? ''));
            $newName = LetterStore::parseName((string) ($body['name'] ?? ''));
        } catch (\InvalidArgumentException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        $old = $letters->paragraphs();
        $oldName = $letters->name();
        if ($new === $old && $newName === $oldName) {
            return Response::json(['paragraphs' => $new, 'name' => $newName, 'changed' => false]);
        }

        // 1. Keep the old message: upload it as a file first. If that fails nothing changes and the old message stays.
        try {
            [$archivedKey, $where] = $this->archive($old, $oldName);
        } catch (StorageException $e) {
            error_log('[letter] archiving the old message failed: ' . $e->getMessage());
            return Response::json(['message' => $e->getMessage() . ' The message was not changed. Check the storage settings in .env.'], 502);
        }

        // 2. Only then show the new one.
        try {
            $letters->set($new, $newName, $archivedKey);
        } catch (\RuntimeException $e) {
            error_log('[letter] ' . $e->getMessage());
            return Response::json(['message' => 'Could not save the message: storage/app is not writable.'], 500);
        }

        return Response::json(['paragraphs' => $new, 'name' => $newName, 'changed' => true, 'archivedTo' => $where]);
    }

    /**
     * Writes the old message to a text file in Spaces (private). Without Spaces credentials it falls back to
     * storage/app/letters on the server.
     *
     * @param list<string> $paragraphs
     * @param string $recipient who the old letter was for
     * @return array{string,string} [key, 'spaces'|'server']
     */
    private function archive(array $paragraphs, string $recipient): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $name = 'messages/' . $now->format('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.txt';
        $contents = "Message replaced on " . $now->format('c') . "\n"
            . 'A letter for: ' . $recipient . "\n"
            . str_repeat('-', 40) . "\n\n"
            . implode("\n\n", $paragraphs) . "\n";

        try {
            $disk = storage('spaces');
        } catch (\InvalidArgumentException) {
            $disk = null;
        }

        if ($disk === null) {
            $key = 'letters/' . substr($name, strlen('messages/'));
            storage('local')->put($key, $contents);
            return [$key, 'server'];
        }

        $folder = trim((string) config('filesystems.disks.spaces.folder'), '/');
        $key = ($folder !== '' ? $folder . '/' : '') . $name;
        $disk instanceof PrivateWriter ? $disk->putPrivate($key, $contents) : $disk->put($key, $contents);
        return [$key, 'spaces'];
    }
}
