<?php
/**
 * @copyright: Copyright © 2017 Firebear Studio. All rights reserved.
 * @author   : Firebear Studio <fbeardev@gmail.com>
 */

namespace Firebear\ImportExport\Console\Command;

use Firebear\ImportExport\Api\JobRepositoryInterface;
use Firebear\ImportExport\Model\Job\Handler\HandlerPoolInterface;
use Firebear\ImportExport\Model\Job\Handler\HandlerInterface;
use Firebear\ImportExport\Model\Job\Processor;
use Firebear\ImportExport\Model\JobFactory;
use Firebear\ImportExport\Helper\Data as Helper;
use Firebear\ImportExport\Logger\Logger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Magento\Backend\App\Area\FrontNameResolver;
use Magento\Framework\App\State;

/**
 * Command prints list of available currencies
 */
class ImportJobRunCommand extends ImportJobAbstractCommand
{
    /**
     * @var HandlerPoolInterface
     */
    private $handlerPool;

    /**
     * Constructor
     *
     * @param JobFactory $factory
     * @param JobRepositoryInterface $repository
     * @param Logger $logger
     * @param Processor $importProcessor
     * @param Helper $helper
     * @param State $state
     * @param HandlerPoolInterface $handlerPool
     */
    public function __construct(
        JobFactory $factory,
        JobRepositoryInterface $repository,
        Logger $logger,
        Processor $importProcessor,
        Helper $helper,
        State $state,
        HandlerPoolInterface $handlerPool
    ) {
        $this->handlerPool = $handlerPool;

        parent::__construct(
            $factory,
            $repository,
            $logger,
            $importProcessor,
            $helper,
            $state
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('import:job:run')
            ->setDescription('Generate Firebear Import Jobs')
            ->setDefinition(
                [
                    new InputArgument(
                        self::JOB_ARGUMENT_NAME,
                        InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
                        'Space-separated list of import job ids or omit to generate all jobs.'
                    )
                ]
            );

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $time = explode(" ", microtime());
        $startTime = $time[0] + $time[1];

        $isAreaCode = 0;
        try {
            if ($this->state->getAreaCode()) {
                $isAreaCode = 1;
            }
        } catch (\Exception $e) {
            $isAreaCode = 0;
        }
        if (!$isAreaCode) {
            $this->state->setAreaCode(FrontNameResolver::AREA_CODE);
        }
        $requestedIds = $input->getArgument(self::JOB_ARGUMENT_NAME);
        $requestedIds = array_filter(array_map('trim', $requestedIds), 'strlen');
        $jobCollection = $this->factory->create()->getCollection();
        $jobCollection->addFieldToFilter('is_active', 1);
        if ($requestedIds) {
            $jobCollection->addFieldToFilter('entity_id', ['in' => $requestedIds]);
        }
        if ($jobCollection->getSize()) {
            foreach ($jobCollection as $job) {
                $noProblems = 0;
                $id = (int)$job->getEntityId();
                $file = $this->helper->beforeRun($id);
                $history = $this->helper->createHistory($id, $file, 'console');
                $this->processor->debugMode = $this->debugMode = $this->helper->getDebugMode();
                $this->processor->inConsole = 1;
                $this->processor->setLogger($this->helper->getLogger());
                $this->processor->processScope($id, $file);
                $counter = $this->helper->countData($file, $job->getId());
                $error = 0;
                $result = false;
                for ($i = 0; $i < $counter; $i++) {
                    list($count, $result) = $this->helper->processImport($file, $job->getId(), $i, $error, 0);
                    $error += $count;
                    if (!$result) {
                        $noProblems = 1;
                        break;
                    }
                }
                if (!$noProblems && $this->processor->reindex) {
                    $this->processor->processReindex($file, $id);
                }
                $this->processor->showErrors();
                $this->processor->getImportModel()->getErrorAggregator()->clear();
                $this->processor->getImportModel()->setNullEntityAdapter();
                $this->helper->saveFinishHistory($history);

                /** @var HandlerInterface $handler */
                foreach ($this->handlerPool->getHandlersInstances() as $handler) {
                    $handler->execute($job, $file, (int)$result);
                }
            }
        } else {
            $this->addLogComment('No jobs found', $output, 'error');
        }

        $time = explode(" ", microtime());
        $endTime = $time[0] + $time[1];
        $totalTime = $endTime - $startTime;
        $totalTime = round($totalTime, 5);
        $this->addLogComment("------------" .$totalTime, $output, 'info');
    }
}
