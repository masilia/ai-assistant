<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\EventListener;

use Masilia\AiAssistant\Agent\Tool\TempFileRegistry;
use Symfony\Component\HttpKernel\Event\KernelEvent;

/**
 * Flushes tracked temp image files at the end of the HTTP request cycle.
 *
 * ImageTransformer writes generated images to temp files and registers
 * them via TempFileRegistry::track(). If a request ends (successfully
 * or with an exception) and the temp files are not cleaned up, they
 * leak on disk until the system tmp cleaner runs.
 *
 * Listens on TWO events so temp files are cleaned even when the
 * controller throws:
 *   - `kernel.terminate` — the normal path
 *   - `kernel.exception` — fires before the exception bubbles, so files
 *     queued mid-request (e.g. content creation failed after image gen)
 *     are still removed.
 */
final class TempFileFlushListener
{
    public function __invoke(KernelEvent $event): void
    {
        TempFileRegistry::flush();
    }
}
