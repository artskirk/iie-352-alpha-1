<?php
/**
 * @copyright: Copyright © 2017 Firebear Studio. All rights reserved.
 * @author   : Firebear Studio <fbeardev@gmail.com>
 */

namespace Firebear\ImportExport\Cron;

use Firebear\ImportExport\Model\Job\Processor;
use Firebear\ImportExport\Model\Job\Handler\HandlerPoolInterface;
use Firebear\ImportExport\Model\Job\Handler\HandlerInterface;
use Firebear\ImportExport\Helper\Data as Helper;

/**
 * Sales entity grids indexing observer.
 *
 * Performs handling cron jobs related to indexing
 * of Order, Invoice, Shipment and Creditmemo grids.
 */
class RunImportJobs
{
    /**
     * @var Processor
     */
    protected $processor;

    /**
     * @var Helper
     */
    protected $helper;

    /**
     * @var bool
     */
    protected $debugMode;

    /**
     * @var HandlerPoolInterface
     */
    private $handlerPool;

    /**
     * RunImportJobs constructor.
     *
     * @param Processor $importProcessor
     * @param Helper $helper
     * @param HandlerPoolInterface $handlerPool
     */
    public function __construct(
        Processor $importProcessor,
        Helper $helper,
        HandlerPoolInterface $handlerPool
    ) {
        $this->helper = $helper;
        $this->processor = $importProcessor;
        $this->handlerPool = $handlerPool;
    }

    /**
     * @param $schedule
     *
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute($schedule)
    {
        $jobCode = $schedule->getJobCode();

        preg_match('/_id_([0-9]+)/', $jobCode, $matches);
        if (isset($matches[1]) && (int)$matches[1] > 0) {
            $noProblems = 0;
            $jobId = (int)$matches[1];
            $result = false;
            $file = $this->helper->beforeRun($jobId);
            $history = $this->helper->createHistory($jobId, $file, 'console');
            $this->processor->debugMode = $this->debugMode = $this->helper->getDebugMode();
            $this->processor->inConsole = 1;
            $this->processor->setLogger($this->helper->getLogger());
            $this->processor->processScope($jobId, $file);
            $counter = $this->helper->countData($file, $jobId);
            $error = 0;

            for ($i = 0; $i < $counter; $i++) {
                list($count, $result) = $this->helper->processImport($file, $jobId, $i, $error, 0);
                $error += $count;
                if (!$result) {
                    $noProblems = 1;
                    break;
                }
            }

            if (!$noProblems && $this->processor->reindex) {
                $this->processor->processReindex($file, $jobId);
            }
            $this->processor->showErrors();
            $this->processor->getImportModel()->getErrorAggregator()->clear();
            $this->processor->getImportModel()->setNullEntityAdapter();
            $this->helper->saveFinishHistory($history);

            /** @var HandlerInterface $handler */
            foreach ($this->handlerPool->getHandlersInstances() as $handler) {
                $handler->execute($this->processor->getJob(), $file, (int)$result);
            }
            return true;
        }

        return false;
    }
}
