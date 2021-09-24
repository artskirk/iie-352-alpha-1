<?php
/**
 * @copyright: Copyright © 2021 Firebear Studio. All rights reserved.
 * @author: Firebear Studio <fbeardev@gmail.com>
 */
namespace Firebear\ImportExport\Model\Job\Handler;

use Firebear\ImportExport\Api\Data\ImportInterface;
use Firebear\ImportExport\Model\Job\Processor;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use ZipArchive as Archive;

/**
 * @api
 */
class CompressHandler implements HandlerInterface
{
    /**
     * @var Processor
     */
    private $processor;

    /**
     * @var Archive
     */
    private $archive;

    /**
     * @var TimezoneInterface
     */
    private $timeZone;

    /**
     * @param Processor $importProcessor
     * @param Archive $archive
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        Processor $processor,
        Archive $archive,
        TimezoneInterface $timezone
    ) {
        $this->processor = $processor;
        $this->archive = $archive;
        $this->timeZone = $timezone;
    }

    /**
     * Execute the handler
     *
     * @param ImportInterface $job
     * @param string $file
     * @param int $status
     * @return void
     */
    public function execute(ImportInterface $job, $file, $status)
    {
        $data = $job->getSourceData();
        if (!empty($data['archive_file_after_import'])) {
            $import = $this->processor->getImportModel();
            $platform = $import->getPlatform($data['platforms'] ?? null, $job->getEntity());

            $isGateway = $platform && $platform->isGateway();
            if (!$isGateway) {
                if ($import->getSource()->isRemote()) {
                    $path = $import->getSource()->getTempFilePath();
                    if ($this->compress($path)) {
                        /* remove uploaded temp file */
                        $import->getSource()->resetSource();
                    }
                }
            }
        }
    }

    /**
     * Compress file
     *
     * @param string $path
     * @return bool
     */
    private function compress($path)
    {
        $newPath = $this->getFilePath($path);
        $open = $this->archive->open($newPath, Archive::CREATE);
        if (true === $open) {
            $this->archive->addFile($path, basename($path));
            return $this->archive->close();
        }
        return false;
    }

    /**
     * Return new file path
     *
     * @param string $path
     * @return string
     */
    private function getFilePath($path)
    {
        $date = $this->timeZone->date()->format('Y-m-d-H:i:s');
        return $path . '-' . $date . '.zip';
    }
}
