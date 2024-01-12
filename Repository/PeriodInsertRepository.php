<?php
/*
 * This file is part of the PeriodInsertBundle.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PeriodInsertBundle\Repository;

use App\Entity\Timesheet;
use App\Repository\TimesheetRepository;
use DateInterval;
use DatePeriod;
use DateTime;
use Exception;
use KimaiPlugin\PeriodInsertBundle\Entity\PeriodInsert;
use Psr\Log\LoggerInterface;

class PeriodInsertRepository
{
    /**
     * @var TimesheetRepository
     */
    private $timesheetRepository;
    /**
     * @var string
     */
    private $dateTimeFormat = 'Y-m-d H:i:s';
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * PeriodInsertRepository constructor.
     * @param TimesheetRepository $timesheetRepository
     * @param LoggerInterface $logger
     */
    public function __construct(TimesheetRepository $timesheetRepository, LoggerInterface $logger)
    {
        $this->timesheetRepository = $timesheetRepository;
        $this->logger = $logger;
    }

    /**
     * @param PeriodInsert $entity
     * @return void
     * @throws Exception
     */
    public function saveTimesheet(PeriodInsert $entity)
    {
        $daysToSave = $this->getDatesFromRange($entity->getBegin(), $entity->getEnd());

        foreach ($daysToSave AS $dayToSave) {
            $this->createTimesheet($entity, $dayToSave, $entity->getDurationPerDay());
        }
    }

    /**
     * @param $start
     * @param DateTime $end
     * @param string $format
     * @return array
     * @throws Exception
     */
    private function getDatesFromRange(DateTime $start, DateTime $end, $format = 'Y-m-d'): array
    {
        $start = $start->format($format);
        $end = $end->format($format);
        $return = [];
        $interval = new DateInterval('P1D');
        $realEnd = new DateTime($end);
        $realEnd->add($interval);
        $period = new DatePeriod(new DateTime($start), $interval, $realEnd);

        foreach ($period as $date) {
            $return[] = $date->format($format);
        }

        return $return;
    }

    /**
     * @param PeriodInsert $sheet
     * @param string $begin
     * @param int $duration
     * @param int $minutesPerDay
     * @return void
     * @throws Exception
     */
    protected function createTimesheet(PeriodInsert $sheet, string $begin, int $duration): void
    {
        $begin = $begin . '07:00:00';
        $entry = new Timesheet();
        $entry->setUser($sheet->getUser());
        $entry->setBegin(new DateTime($begin));
        $entry->setDescription($sheet->getDescription());
        foreach ($sheet->getTags() as $tag) {
            $entry->addTag($tag);
        }

        if (null !== $sheet->getProject()) {
            $entry->setProject($sheet->getProject());
        }

        if (null !== $sheet->getActivity()) {
            $entry->setActivity($sheet->getActivity());
        }

        $entry->setEnd((new DateTime($begin))->add(new DateInterval('PT' . $duration . 'S')));
        $entry->setDuration(strtotime($entry->getEnd()->format($this->dateTimeFormat)) - strtotime($entry->getBegin()->format($this->dateTimeFormat)));

        if (null !== $sheet->getFixedRate()) {
            $entry->setFixedRate($sheet->getFixedRate());
        }
        
        if (null !== $sheet->getHourlyRate()) {
            $entry->setHourlyRate($sheet->getHourlyRate());
        }

        if (null !== $sheet->getBillableMode()) {
            $entry->setBillableMode($sheet->getBillableMode());
        }

        if (null !== $sheet->getExported()) {
            $entry->setExported($sheet->getExported());
        }

        try {
            $this->timesheetRepository->save($entry);
        } catch (Exception $ex) {
            $this->logger->error($ex->getMessage());
        }
    }

    /**
     * @return PeriodInsert
     */
    public function getTimesheet(): PeriodInsert
    {
        $entity = new PeriodInsert();
        return $entity;
    }
}
