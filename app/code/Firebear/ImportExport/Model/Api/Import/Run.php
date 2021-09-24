<?php
/**
 * @copyright: Copyright © 2020 Firebear Studio. All rights reserved.
 * @author: Firebear Studio <fbeardev@gmail.com>
 */
namespace Firebear\ImportExport\Model\Api\Import;

use Firebear\ImportExport\Api\Import\RunInterface;
use Firebear\ImportExport\Model\Job\Processor;
use Firebear\ImportExport\Model\Email\Sender;
use Firebear\ImportExport\Helper\Data as Helper;
use Psr\Log\LoggerInterface;

/**
 * Job run command (Service Provider Interface - SPI)
 *
 * @api
 */
class Run implements RunInterface
{
    /**
     * @var Processor
     */
    private $processor;

    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var Sender
     */
    private $sender;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Initialize command
     *
     * @param Processor $processor
     * @param LoggerInterface $logger
     * @param Helper $helper
     * @param Sender $sender
     */
    public function __construct(
        Processor $processor,
        LoggerInterface $logger,
        Helper $helper,
        Sender $sender
    ) {
        $this->processor = $processor;
        $this->logger = $logger;
        $this->helper = $helper;
        $this->sender = $sender;
    }

    /**
     * Run import
     *
     * @param int $jobId
     * @param string $file
     * @param string $type
     * @return bool
     */
    public function execute($jobId, $file, $type = 'webapi')
    {
        $result = false;
        $history = $this->helper->createHistory($jobId, $file, $type);
        try {
            $this->processor->setDebugMode($this->helper->getDebugMode());
            $this->processor->setLogger($this->helper->getLogger());
            $result = $this->processor->processScope($jobId, $file);
        } catch (\Exception $e) {
            $result = false;
            $this->logger->critical($e);
        } finally {
            $this->helper->saveFinishHistory($history);
            $this->sender->sendEmail($this->processor->getJob(), $file, (int)$result);
        }
        return $result;
    }
}
