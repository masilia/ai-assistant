<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Command;

use Exception;
use Ibexa\AutomatedTranslation\Translator as AutomatedTranslator;
use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\Repository\Values\Content\LocationQuery;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion\ContentTypeIdentifier;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion\LogicalAnd;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion\Subtree;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\SortClause\Location\Path;
use Ibexa\Contracts\Core\Repository\Values\Content\Search\SearchHit;
use Masilia\AiAssistant\Client\RequestLoggerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\ProgressBar;
use Throwable;
use Ibexa\Contracts\Core\Repository\Iterator\BatchIterator;
use Ibexa\Contracts\Core\Repository\Iterator\BatchIteratorAdapter\LocationSearchAdapter;

/**
 * Translates every translatable content item in a subtree from one language
 * to another using the configured AI client (Masilia\AiAssistant\Client\AiClient),
 * bridged through ibexa/automated-translation's Encoder.
 *
 * Siteaccess is supplied via Symfony's global --siteaccess option (handled by
 * Ibexa's ConsoleCommandListener); the AI client resolves its provider/model
 * against that siteaccess.
 */
final class TranslateSubtreeCommand extends Command
{
    private const SERVICE_KEY = 'ai';
    private const BATCH_SIZE = 50;

    protected static $defaultName = 'masilia:ai:translate:subtree';
    protected static $defaultDescription = 'Translate all translatable content in a subtree using the AI assistant.';

    public function __construct(
        private readonly Repository             $repository,
        private readonly AutomatedTranslator    $translator,
        private readonly RequestLoggerInterface $requestLogger,
        private readonly LoggerInterface        $aiLogger,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addArgument('location-id', InputArgument::REQUIRED, 'Root location ID of the subtree')
            ->addArgument('from-language', InputArgument::REQUIRED, 'Source language code (e.g. eng-GB)')
            ->addArgument('to-language', InputArgument::REQUIRED, 'Target language code (e.g. fre-FR)')
            ->addOption(
                'type',
                't',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Restrict to content type identifiers (repeatable). Omit for all.',
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Overwrite an existing target translation (default: skip).',
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Report what would be translated without writing anything.',
            )
            ->addOption(
                'copy',
                null,
                InputOption::VALUE_NONE,
                'Copy source content as-is to the target language (no AI).',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $locationId = (int)$input->getArgument('location-id');
            $fromLanguage = (string)$input->getArgument('from-language');
            $toLanguage = (string)$input->getArgument('to-language');
            $typeFilters = $input->getOption('type');
            $force = (bool)$input->getOption('force');
            $dryRun = (bool)$input->getOption('dry-run');
            $copy = (bool)$input->getOption('copy');
        } catch (Throwable $e) {
            $io->error(sprintf('Invalid input: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        try {
            return $this->repository->sudo(
                fn() => $this->process(
                    $locationId,
                    $fromLanguage,
                    $toLanguage,
                    $typeFilters,
                    $force,
                    $dryRun,
                    $copy,
                    $io,
                    $output,
                )
            );
        } catch (Throwable $e) {
            $this->aiLogger->error('Subtree translation failed.', ['exception' => $e]);
            $io->error(sprintf('Translation failed: %s', $e->getMessage()));

            return Command::FAILURE;
        } finally {
            // Console commands do not fire kernel.terminate, so flush manually.
            try {
                $this->requestLogger->flush();
            } catch (Throwable $e) {
                $this->aiLogger->warning('Failed to flush request logger.', ['exception' => $e]);
            }
        }
    }


    private function process(
        int             $locationId,
        string          $fromLanguage,
        string          $toLanguage,
        array           $typeFilters,
        bool            $force,
        bool            $dryRun,
        bool            $copy,
        SymfonyStyle    $io,
        OutputInterface $output,
    ): int
    {
        $locationService = $this->repository->getLocationService();
        $contentService = $this->repository->getContentService();
        $searchService = $this->repository->getSearchService();
        $languageService = $this->repository->getContentLanguageService();

        // Validate languages early — these throw if missing.
        $languageService->loadLanguage($fromLanguage);
        $languageService->loadLanguage($toLanguage);

        $rootLocation = $locationService->loadLocation($locationId);

        $io->section(sprintf(
            'Subtree %s | %s → %s | service=%s | force=%s | dry-run=%s | copy=%s',
            $rootLocation->pathString,
            $fromLanguage,
            $toLanguage,
            self::SERVICE_KEY,
            $force ? 'yes' : 'no',
            $dryRun ? 'yes' : 'no',
            $copy ? 'yes' : 'no',
        ));

        $query = $this->buildQuery($rootLocation, $typeFilters);

        // First, count to drive the progress bar.
        $countQuery = clone $query;
        $countQuery->limit = 0;
        $countQuery->performCount = true;
        $total = $searchService->findLocations($countQuery)->totalCount ?? 0;

        if ($total === 0) {
            $io->warning('No locations match the subtree + filters.');

            return Command::SUCCESS;
        }

        $io->text(sprintf('Found %d locations to inspect.', $total));

        $iterator = new BatchIterator(
            new LocationSearchAdapter($searchService, $query),
            self::BATCH_SIZE,
        );

        $progress = new ProgressBar($output, $total);
        $progress->start();

        $translated = 0;
        $skipped = 0;
        $failed = 0;
        $sampleLogged = false;

        foreach ($iterator as $hit) {
            if (!$hit instanceof SearchHit) {
                continue;
            }
            $progress->advance();

            $location = $hit->valueObject;
            if (!$location instanceof Location) {
                continue;
            }

            try {
                $content = $contentService->loadContent(
                    $location->contentId,
                    [$fromLanguage],
                );
            } catch (Throwable $e) {
                $this->aiLogger->warning('Could not load content for translation.', [
                    'contentId' => $location->contentId,
                    'from' => $fromLanguage,
                    'exception' => $e->getMessage(),
                ]);
                $failed++;
                continue;
            }

            $alreadyTranslated = in_array($toLanguage, $content->getVersionInfo()->languageCodes, true);
            if ($alreadyTranslated && !$force) {
                if ($io->isVeryVerbose()) {
                    $io->text(sprintf('[skip] content #%d already has %s', $content->id, $toLanguage));
                }
                $skipped++;
                continue;
            }

            if ($dryRun) {
                if (!$sampleLogged) {
                    $this->reportDryRunSample($io, $content, $fromLanguage, $toLanguage);
                    $sampleLogged = true;
                } else {
                    $io->text(sprintf('[dry-run] would translate content #%d (%s)', $content->id, $content->getName()));
                }
                $translated++;
                continue;
            }

            try {
                if ($copy) {
                    $this->copyContent($contentService, $content, $fromLanguage, $toLanguage);
                } else {
                    $draft = $this->translator->getTranslatedContent(
                        $fromLanguage,
                        $toLanguage,
                        self::SERVICE_KEY,
                        $content,
                    );
                    $this->publishTranslatedDraft($contentService, $draft->getVersionInfo()->versionNo, $toLanguage, $content->id);
                }
                $translated++;
            } catch (Throwable $e) {
                $this->aiLogger->warning('Translation failed for content.', [
                    'contentId' => $content->id,
                    'exception' => $e->getMessage(),
                ]);
                if ($io->isVerbose()) {
                    $io->text(sprintf('[fail] content #%d: %s', $content->id, $e->getMessage()));
                }
                $failed++;
            }
        }

        $progress->finish();
        $io->newLine(2);

        $io->success(sprintf(
            'Done. translated=%d  skipped=%d  failed=%d  (total inspected=%d)',
            $translated,
            $skipped,
            $failed,
            $total,
        ));

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function buildQuery(Location $rootLocation, array $typeFilters): LocationQuery
    {
        $criteria = [
            new Subtree($rootLocation->pathString),
            new Criterion\Location\IsMainLocation(Criterion\Location\IsMainLocation::MAIN),
        ];

        if ($typeFilters !== []) {
            $criteria[] = new ContentTypeIdentifier($typeFilters);
        }

        return new LocationQuery([
            'filter' => new LogicalAnd($criteria),
            'sortClauses' => [new Path()],
            'limit' => self::BATCH_SIZE,
        ]);
    }

    private function reportDryRunSample(SymfonyStyle $io, Content $content, string $from, string $to): void
    {
        $io->text(sprintf('[dry-run] sample content #%d (%s)', $content->id, $content->getName()));
        $io->text(sprintf('  from=%s  to=%s  service=%s', $from, $to, self::SERVICE_KEY));

        try {
            $contentType = $this->repository->getContentTypeService()->loadContentType(
                $content->contentInfo->contentTypeId,
            );
            $fields = [];
            foreach ($contentType->getFieldDefinitions() as $fieldDef) {
                if (!$fieldDef->isTranslatable) {
                    continue;
                }
                $fields[] = sprintf('%s (%s)', $fieldDef->identifier, $fieldDef->fieldTypeIdentifier);
            }
            $io->text(sprintf('  translatable fields: %s', implode(', ', $fields) ?: '(none)'));
        } catch (Exception $e) {
            $io->text('  (could not load content type for field list)');
        }
    }

    private function copyContent(
        ContentService $contentService,
        Content        $sourceContent,
        string         $fromLanguage,
        string         $toLanguage,
    ): void
    {
        $contentType = $sourceContent->getContentType();

        $draft = $contentService->createContentDraft($sourceContent->contentInfo);

        $updateStruct = $contentService->newContentUpdateStruct();
        $updateStruct->initialLanguageCode = $toLanguage;

        foreach ($contentType->getFieldDefinitions() as $fieldDef) {
            if (!$fieldDef->isTranslatable) {
                continue;
            }
            $field = $sourceContent->getField($fieldDef->identifier, $fromLanguage);
            if ($field === null || $field->value === null) {
                continue;
            }
            $updateStruct->setField($fieldDef->identifier, $field->value, $toLanguage);
        }

        $contentService->updateContent($draft->getVersionInfo(), $updateStruct);
        $contentService->publishVersion($draft->getVersionInfo());
    }

    private function publishTranslatedDraft(
        ContentService $contentService,
        int            $versionNo,
        string         $toLanguage,
        int            $contentId,
    ): void
    {
        $contentInfo = $contentService->loadContentInfo($contentId);
        $versionInfo = $contentService->loadVersionInfo($contentInfo, $versionNo);
        $contentService->publishVersion($versionInfo, [$toLanguage]);
    }
}
