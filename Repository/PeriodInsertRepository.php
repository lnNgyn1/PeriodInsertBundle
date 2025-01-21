<?php
/*
 * This file is part of the PeriodInsertBundle.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PeriodInsertBundle\Repository;

use App\Activity\ActivityStatisticService;
use App\Configuration\LocaleService;
use App\Configuration\SystemConfiguration;
use App\Customer\CustomerStatisticService;
use App\Entity\Timesheet;
use App\Model\BudgetStatisticModel;
use App\Project\ProjectStatisticService;
use App\Repository\TimesheetRepository;
use App\Timesheet\RateServiceInterface;
use App\Timesheet\TimesheetService;
use App\Utils\Duration;
use App\Utils\LocaleFormatter;
use App\WorkingTime\WorkingTimeService;
use KimaiPlugin\PeriodInsertBundle\Entity\PeriodInsert;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class PeriodInsertRepository
{
    public function __construct(private TimesheetRepository $timesheetRepository,
        private readonly SystemConfiguration $configuration,
        private readonly CustomerStatisticService $customerStatisticService,
        private readonly ProjectStatisticService $projectStatisticService,
        private readonly ActivityStatisticService $activityStatisticService,
        private readonly RateServiceInterface $rateService,
        private readonly AuthorizationCheckerInterface $security,
        private readonly LocaleService $localeService,
        private readonly WorkingTimeService $workService,
        private readonly TimesheetService $timesheetService,
    ) {
    }

    /**
     * @param PeriodInsert $entity
     * @return string
     */
    public function findDayToInsert(PeriodInsert $entity): string
    {
        for ($end = clone $entity->getEnd(); $end >= $entity->getBegin(); $end->modify('-1 day')) {
            if ($entity->isDayValid($end)) {
                return $end->format('Y-m-d');
            }
        }
        return '';
    }

    /**
     * @param PeriodInsert $entity
     * @param string $dayToInsert
     * @return bool
     */
    public function checkFutureTime(PeriodInsert $entity, string $dayToInsert): bool
    {
        if ($this->configuration->isTimesheetAllowFutureTimes()) {
            return false;
        }

        if ($dayToInsert !== date('Y-m-d')) {
            return $dayToInsert > date('Y-m-d');
        }

        $now = new \DateTime('now', $entity->getBeginTime()->getTimezone());

        // allow configured default rounding time + 1 minute
        $nowBeginTs = $now->getTimestamp() + ($this->configuration->getTimesheetDefaultRoundingBegin() * 60) + 60;
        $nowEndTs = $now->getTimestamp() + ($this->configuration->getTimesheetDefaultRoundingEnd() * 60) + 60;

        return $nowBeginTs < $entity->getBeginTime()->getTimestamp() || $nowEndTs < $entity->getEndTime()->getTimestamp();
    }

    /**
     * @param PeriodInsert $entity
     * @return bool
     */
    public function checkZeroDuration(PeriodInsert $entity): bool
    {
        if ($this->configuration->isTimesheetAllowZeroDuration()) {
            return false;
        }
        return $entity->getDuration() === 0;
    }

    /**
     * @param PeriodInsert $entity
     * @return string
     */
    public function checkOverlappingTimeEntries(PeriodInsert $entity): string
    {   
        if (!$this->configuration->isTimesheetAllowOverlappingRecords()) {
            for ($begin = clone $entity->getBegin(); $begin <= $entity->getEnd(); $begin->modify('+1 day')) {
                if ($entity->isDayValid($begin) && $this->timesheetRepository->hasRecordForTime($this->createTimesheet($entity, $begin))) {
                    return $begin->format('m/d/Y');
                }
            }
        }
        return '';
    }

    /**
     * @param PeriodInsert $entity
     * @return string
     */
    public function checkBudgetOverbooked(PeriodInsert $entity): string
    {   
        if ($this->configuration->isTimesheetAllowOverbookingBudget()) {
            return '';
        }

        if (!$entity->isBillable()) {
            return '';
        }
        
        if ($entity->getProject() === null) {
            return '';
        }

        $totalValidDays = 0;
        $validDaysPerMonth = [];
        for ($begin = clone $entity->getBegin(); $begin <= $entity->getEnd(); $begin->modify('+1 day')) {
            if ($entity->isDayValid($begin)) {
                $totalValidDays++;
                $validDaysPerMonth[$begin->format('Y-m')] = ($validDaysPerMonth[$begin->format('Y-m')] ?? 0) + 1;
            }
        }

        $recordDate = clone $entity->getBegin();
        $duration = $entity->getDuration();

        $timeRate = $this->rateService->calculate($this->createTimesheet($entity, $recordDate));
        $rate = $timeRate->getRate();

        $now = new \DateTime('now', $recordDate->getTimezone());
        
        foreach ($validDaysPerMonth as $validDaysInMonth) {
            if (null !== ($activity = $entity->getActivity()) && $activity->hasBudgets()) {
                $dateTime = $activity->isMonthlyBudget() ? $recordDate : $now;
                $validDays = $activity->isMonthlyBudget() ? $validDaysInMonth : $totalValidDays;
                $stat = $this->activityStatisticService->getBudgetStatisticModel($activity, $dateTime);
                if (($message = $this->checkBudgets($stat, $entity, $duration, $rate, $validDays, 'activity')) !== '') {
                    return $message;
                }
            }
    
            if (null !== ($project = $entity->getProject())) {
                if ($project->hasBudgets()) {
                    $dateTime = $project->isMonthlyBudget() ? $recordDate : $now;
                    $validDays = $project->isMonthlyBudget() ? $validDaysInMonth : $totalValidDays;
                    $stat = $this->projectStatisticService->getBudgetStatisticModel($project, $dateTime);
                    if (($message = $this->checkBudgets($stat, $entity, $duration, $rate, $validDays, 'project')) !== '') {
                        return $message;
                    }
                }
                if (null !== ($customer = $project->getCustomer()) && $customer->hasBudgets()) {
                    $dateTime = $customer->isMonthlyBudget() ? $recordDate : $now;
                    $validDays = $customer->isMonthlyBudget() ? $validDaysInMonth : $totalValidDays;
                    $stat = $this->customerStatisticService->getBudgetStatisticModel($customer, $dateTime);
                    if (($message = $this->checkBudgets($stat, $entity, $duration, $rate, $validDays, 'customer')) !== '') {
                        return $message;
                    }
                }
            }
            $recordDate->modify('+1 month');
        }
        return '';
    }

    /**
     * @param BudgetStatisticModel $stat
     * @param PeriodInsert $entity
     * @param int $duration
     * @param float $rate
     * @param int $validDays
     * @param string $field
     * @return string
     */
    protected function checkBudgets(BudgetStatisticModel $stat, PeriodInsert $entity, int $duration, float $rate, int $validDays, string $field): string
    {
        $fullRate = $stat->getBudgetSpent() + $rate * $validDays;

        if ($stat->hasBudget() && $fullRate > $stat->getBudget()) {
            return $this->getBudgetViolationMessage($entity, $field, $stat->getBudget(), $stat->getBudgetSpent());
        }

        $fullDuration = $stat->getTimeBudgetSpent() + $duration * $validDays;

        if ($stat->hasTimeBudget() && $fullDuration > $stat->getTimeBudget()) {
            return $this->getTimeBudgetViolationMessage($field, $stat->getTimeBudget(), $stat->getTimeBudgetSpent());
        }

        return '';
    }

    /**
     * @param PeriodInsert $entity
     * @param string $field
     * @param float $budget
     * @param float $rate
     * @return string
     */
    protected function getBudgetViolationMessage(PeriodInsert $entity, string $field, float $budget, float $rate): string
    {
        if (!$this->security->isGranted('budget_money', $field)) {
            return 'Sorry, the budget is used up.';
        }
        // using the locale of the assigned user is not the best solution, but allows to be independent of the request stack
        $helper = new LocaleFormatter($this->localeService, $entity->getUser()?->getLocale() ?? 'en');
        $currency = $entity->getProject()->getCustomer()->getCurrency();

        $free = $budget - $rate;
        $free = max($free, 0);
        $used = $helper->money($rate, $currency);
        $budget = $helper->money($budget, $currency);
        $free = $helper->money($free, $currency);

        return 'The budget is used up. Of the available ' . $budget . ', ' . $used . ' has been booked so far, ' . $free . ' can still be used.';
    }

    /**
     * @param string $field
     * @param int $budget
     * @param int $duration
     * @return string
     */
    protected function getTimeBudgetViolationMessage(string $field, int $budget, int $duration): string
    {
        if (!$this->security->isGranted('budget_time', $field)) {
            return 'Sorry, the budget is used up.';
        }
        $durationFormat = new Duration();

        $free = $budget - $duration;
        $free = max($free, 0);

        $used = $durationFormat->format($duration);
        $budget = $durationFormat->format($budget);
        $free = $durationFormat->format($free);

        return 'The budget is used up. Of the available ' . $budget . ', ' . $used . ' has been booked so far, ' . $free . ' can still be used.';
    }

    /**
     * @param PeriodInsert $entity
     * @return string[]
     */
    public function findHolidays(PeriodInsert $entity): array
    {
        $holidays = [];
        for ($begin = clone $entity->getBegin(); $begin <= $entity->getEnd(); $begin->modify('first day of next month')) {
            $month = $this->workService->getMonth($entity->getUser(), $begin, (clone $begin)->modify('last day of this month'));
            foreach ($month->getDays() as $day) {
                if ($day->hasAddons()) {
                    $holidays[] = $day->getDay()->format('Y-m-d');
                }
            }
        }
        return $holidays;
    }

    /**
     * @param PeriodInsert $entity
     * @return void
     */
    public function saveTimesheet(PeriodInsert $entity): void
    {
        $holidays = $this->findHolidays($entity);
        for ($begin = clone $entity->getBegin(); $begin <= $entity->getEnd(); $begin->modify('+1 day')) {
            if ($entity->isDayValid($begin) && $this->workService->getContractMode($entity->getUser())->getCalculator($entity->getUser())->isWorkDay($begin) && !in_array($begin->format('Y-m-d'), $holidays)) {
                $this->timesheetService->saveNewTimesheet($this->createTimesheet($entity, $begin));
            }
        }
    }

    /**
     * @param PeriodInsert $entity
     * @param DateTime $begin
     * @return Timesheet
     */
    protected function createTimesheet(PeriodInsert $entity, \DateTime $begin): Timesheet
    {
        $entry = new Timesheet();
        $entry->setUser($entity->getUser());

        $entry->setBegin((clone $begin));
        $entry->setEnd((clone $begin)->modify('+' . $entity->getDuration() . ' seconds'));
        $entry->setDuration($entity->getDuration());

        if (null !== $entity->getProject()) {
            $entry->setProject($entity->getProject());
        }

        if (null !== $entity->getActivity()) {
            $entry->setActivity($entity->getActivity());
        }

        $entry->setDescription($entity->getDescription());
        
        foreach ($entity->getTags() as $tag) {
            $entry->addTag($tag);
        }

        if (null !== $entity->getFixedRate()) {
            $entry->setFixedRate($entity->getFixedRate());
        }
        
        if (null !== $entity->getHourlyRate()) {
            $entry->setHourlyRate($entity->getHourlyRate());
        }
        
        $entry->setBillable($entity->isBillable());
        $entry->setBillableMode($entity->getBillableMode());
        $entry->setExported($entity->getExported());

        return $entry;
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
