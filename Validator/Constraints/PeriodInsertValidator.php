<?php

/*
 * This file is part of the PeriodInsertBundle.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PeriodInsertBundle\Validator\Constraints;

use App\Activity\ActivityStatisticService;
use App\Configuration\LocaleService;
use App\Configuration\SystemConfiguration;
use App\Customer\CustomerStatisticService;
use App\Model\BudgetStatisticModel;
use App\Project\ProjectStatisticService;
use App\Repository\TimesheetRepository;
use App\Timesheet\RateServiceInterface;
use App\Utils\Duration;
use App\Utils\LocaleFormatter;
use DateTime;
use DateTimeImmutable;
use KimaiPlugin\PeriodInsertBundle\Entity\PeriodInsert as PeriodInsertEntity;
use KimaiPlugin\PeriodInsertBundle\Repository\PeriodInsertRepository;
use KimaiPlugin\PeriodInsertBundle\Validator\Constraints\PeriodInsert as PeriodInsertConstraint;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class PeriodInsertValidator extends ConstraintValidator
{
    public function __construct(private readonly PeriodInsertRepository $repository,
        private readonly TimesheetRepository $timesheetRepository,
        private readonly SystemConfiguration $systemConfiguration,
        private readonly CustomerStatisticService $customerStatisticService,
        private readonly ProjectStatisticService $projectStatisticService,
        private readonly ActivityStatisticService $activityStatisticService,
        private readonly RateServiceInterface $rateService,
        private readonly AuthorizationCheckerInterface $security,
        private readonly LocaleService $localeService
    )
    {
    }

    /**
     * @param mixed|PeriodInsertEntity $value
     * @param Constraint $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!($constraint instanceof PeriodInsertConstraint)) {
            throw new UnexpectedTypeException($constraint, PeriodInsertConstraint::class);
        }

        if (!\is_object($value) || !($value instanceof PeriodInsertEntity)) {
            throw new UnexpectedTypeException($value, PeriodInsertEntity::class);
        }

        $this->validateTimeRange($value);
        $this->validateActivityAndProject($value);
        $this->validateDuration($value);

        // only call validators if period insert has valid days to insert
        if ($this->validatePeriodInsert($value)) {
            $this->validateProjectDates($value);
            $this->validateFutureTimes($value);
            $this->validateOverlapping($value);
            $this->validateBudgetUsed($value);
        }
    }

    /**
     * @param PeriodInsertEntity $periodInsert
     */
    private function validateTimeRange(PeriodInsertEntity $periodInsert): void
    {
        if (null === $periodInsert->getDateRange()) {
            $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::MISSING_TIME_RANGE_ERROR))
                ->atPath('daterange')
                ->setTranslationDomain('validators')
                ->setCode(PeriodInsertConstraint::MISSING_TIME_RANGE_ERROR)
                ->addViolation();
        }
    }

    /**
     * @param PeriodInsertEntity $periodInsert
     */
    private function validateActivityAndProject(PeriodInsertEntity $periodInsert): void
    {
        $activity = $periodInsert->getActivity();

        if ($this->systemConfiguration->isTimesheetRequiresActivity() && null === $activity) {
            $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::MISSING_ACTIVITY_ERROR))
                ->atPath('activity')
                ->setTranslationDomain('validators')
                ->setCode(PeriodInsertConstraint::MISSING_ACTIVITY_ERROR)
                ->addViolation();
        }

        $hasActivity = null !== $activity;

        if ($hasActivity && !$activity->isVisible()) {
            $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::DISABLED_ACTIVITY_ERROR))
                ->atPath('activity')
                ->setTranslationDomain('validators')
                ->setCode(PeriodInsertConstraint::DISABLED_ACTIVITY_ERROR)
                ->addViolation();
        }

        if (null === ($project = $periodInsert->getProject())) {
            $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::MISSING_PROJECT_ERROR))
                ->atPath('project')
                ->setTranslationDomain('validators')
                ->setCode(PeriodInsertConstraint::MISSING_PROJECT_ERROR)
                ->addViolation();

            return;
        }
        
        if (!$project->isVisible()) {
            $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::DISABLED_PROJECT_ERROR))
                ->atPath('project')
                ->setTranslationDomain('validators')
                ->setCode(PeriodInsertConstraint::DISABLED_PROJECT_ERROR)
                ->addViolation();
        }

        if ($hasActivity && null !== $activity->getProject() && $activity->getProject() !== $project) {
            $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::ACTIVITY_PROJECT_MISMATCH_ERROR))
                ->atPath('project')
                ->setTranslationDomain('validators')
                ->setCode(PeriodInsertConstraint::ACTIVITY_PROJECT_MISMATCH_ERROR)
                ->addViolation();
        }

        if ($hasActivity && !$project->isGlobalActivities() && $activity->isGlobal()) {
            $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::PROJECT_DISALLOWS_GLOBAL_ACTIVITY_ERROR))
                ->atPath('activity')
                ->setTranslationDomain('validators')
                ->setCode(PeriodInsertConstraint::PROJECT_DISALLOWS_GLOBAL_ACTIVITY_ERROR)
                ->addViolation();
        }

        if (null === ($customer = $project->getCustomer())) {
            return;
        }

        if (!$customer->isVisible()) {
            $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::DISABLED_CUSTOMER_ERROR))
                ->atPath('customer')
                ->setTranslationDomain('validators')
                ->setCode(PeriodInsertConstraint::DISABLED_CUSTOMER_ERROR)
                ->addViolation();
        }
    }

    /**
     * @param PeriodInsertEntity $periodInsert
     */
    private function validateDuration(PeriodInsertEntity $periodInsert): void
    {
        if ($this->systemConfiguration->isTimesheetAllowZeroDuration()) {
            return;
        }

        if (null !== $periodInsert->getDuration() && $periodInsert->getDuration() === 0) {
            $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::ZERO_DURATION_ERROR))
                ->atPath('duration')
                ->setTranslationDomain('validators')
                ->setCode(PeriodInsertConstraint::ZERO_DURATION_ERROR)
                ->addViolation();
        }

        if (null !== $periodInsert->getDuration() && $periodInsert->getDuration() < 0) {
            $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::NEGATIVE_DURATION_ERROR))
                ->atPath('duration')
                ->setTranslationDomain('validators')
                ->setCode(PeriodInsertConstraint::NEGATIVE_DURATION_ERROR)
                ->addViolation();
        }
    }

    /**
     * @param PeriodInsertEntity $periodInsert
     * @return bool
     */
    private function validatePeriodInsert(PeriodInsertEntity $periodInsert): bool
    {
        if (!$periodInsert->getValidDays()) {
            $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::MISSING_DAY_ERROR))
                ->atPath('daterange')
                ->setTranslationDomain('validators')
                ->setCode(PeriodInsertConstraint::MISSING_DAY_ERROR)
                ->addViolation();

            return false;
        }

        return true;
    }

    /**
     * @param PeriodInsertEntity $periodInsert
     */
    private function validateProjectDates(PeriodInsertEntity $periodInsert): void
    {
        if (null === ($project = $periodInsert->getProject())) {
            return;
        }

        $projectBegin = $project->getStart();
        $projectEnd = $project->getEnd();

        if (null === $projectBegin && null === $projectEnd) {
            return;
        }

        $validDays = $periodInsert->getValidDays();
        $periodInsertStart = reset($validDays);
        $periodInsertEnd = end($validDays)->modify('+' . $periodInsert->getDuration() . ' seconds');

        if (null !== $projectBegin && ($periodInsertStart->getTimestamp() < $projectBegin->getTimestamp() || $periodInsertEnd < $projectBegin)) {
            $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::PROJECT_NOT_STARTED_ERROR) . ' It starts on ' . $projectBegin->format('n/j/Y') . '.')
                ->atPath('daterange')
                ->setTranslationDomain('validators')
                ->setCode(PeriodInsertConstraint::PROJECT_NOT_STARTED_ERROR)
                ->addViolation();
        }

        if (null !== $projectEnd && ($periodInsertStart->getTimestamp() > $projectEnd->getTimestamp() || $periodInsertEnd > $projectEnd)) {
            $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::PROJECT_ALREADY_ENDED_ERROR) . ' It ends on ' . $projectEnd->format('n/j/Y') . '.')
                ->atPath('daterange')
                ->setTranslationDomain('validators')
                ->setCode(PeriodInsertConstraint::PROJECT_ALREADY_ENDED_ERROR)
                ->addViolation();
        }
    }

    /**
     * @param PeriodInsertEntity $periodInsert
     */
    private function validateFutureTimes(PeriodInsertEntity $periodInsert): void
    {
        if ($this->systemConfiguration->isTimesheetAllowFutureTimes()) {
            return;
        }

        $validDays = $periodInsert->getValidDays();
        $lastValidDay = end($validDays);
        $now = new DateTime('now', $lastValidDay->getTimezone());

        if ($lastValidDay->format('Y-m-d') < $now->format('Y-m-d')) {
            return;
        }

        if ($lastValidDay->format('Y-m-d') > $now->format('Y-m-d')) {
            $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::TIME_RANGE_IN_FUTURE_ERROR))
                ->atPath('daterange')
                ->setTranslationDomain('validators')
                ->setCode(PeriodInsertConstraint::TIME_RANGE_IN_FUTURE_ERROR)
                ->addViolation();
            
            return;
        }

        // allow configured default rounding time + 1 minute
        $nowBeginTs = $now->getTimestamp() + ($this->systemConfiguration->getTimesheetDefaultRoundingBegin() * 60) + 60;
        $nowEndTs = $now->getTimestamp() + ($this->systemConfiguration->getTimesheetDefaultRoundingEnd() * 60) + 60;

        // if last day of period insert is today, check the begin and end time
        if ($nowBeginTs < $lastValidDay->getTimestamp()) {
            $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::BEGIN_IN_FUTURE_ERROR))
                ->atPath('begin_time')
                ->setTranslationDomain('validators')
                ->setCode(PeriodInsertConstraint::BEGIN_IN_FUTURE_ERROR)
                ->addViolation();
        } elseif ($nowEndTs < $lastValidDay->getTimestamp() + $periodInsert->getDuration()) {
            $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::END_IN_FUTURE_ERROR))
                ->atPath('duration')
                ->setTranslationDomain('validators')
                ->setCode(PeriodInsertConstraint::END_IN_FUTURE_ERROR)
                ->addViolation();
        }
    }

    /**
     * @param PeriodInsertEntity $periodInsert
     */
    private function validateOverlapping(PeriodInsertEntity $periodInsert): void
    {
        if ($this->systemConfiguration->isTimesheetAllowOverlappingRecords()) {
            return;
        }

        $overlappingDates = [];

        foreach ($periodInsert->getValidDays() as $day) {
            if ($this->timesheetRepository->hasRecordForTime($this->repository->createTimesheet($periodInsert, $day))) {
                $overlappingDates[] = $day->format('n/j/Y');
            }
        }

        if ($overlappingDates) {
            $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::RECORD_OVERLAPPING_ERROR) . implode(', ', $overlappingDates) . '.')
                ->atPath('daterange')
                ->setTranslationDomain('validators')
                ->setCode(PeriodInsertConstraint::RECORD_OVERLAPPING_ERROR)
                ->addViolation();
        }
    }

    /**
     * @param PeriodInsertEntity $periodInsert
     */
    private function validateBudgetUsed(PeriodInsertEntity $periodInsert): void
    {   
        if ($this->systemConfiguration->isTimesheetAllowOverbookingBudget()) {
            return;
        }

        if (!$periodInsert->isBillable()) {
            return;
        }
        
        if (null === ($project = $periodInsert->getProject())) {
            return;
        }

        // only check budget if duration is not negative
        if (null !== $periodInsert->getDuration() && $periodInsert->getDuration() >= 0) {
            return;
        }

        $validDaysPerMonth = [];

        foreach ($periodInsert->getValidDays() as $day) {
            $month = $day->format('F Y');
            $validDaysPerMonth[$month] = ($validDaysPerMonth[$month] ?? 0) + 1;
        }
        
        $recordDate = DateTimeImmutable::createFromMutable($periodInsert->getBegin());
        $now = new DateTime('now', $recordDate->getTimezone());

        $timeRate = $this->rateService->calculate($this->repository->createTimesheet($periodInsert, $recordDate));
        $rate = $timeRate->getRate();
        $duration = $periodInsert->getDuration();

        $this->checkBudgetsForEntity($validDaysPerMonth, $recordDate, $now, $periodInsert, $rate, $duration, $periodInsert->getActivity(), $this->activityStatisticService, 'activity');
        $this->checkBudgetsForEntity($validDaysPerMonth, $recordDate, $now, $periodInsert, $rate, $duration, $project, $this->projectStatisticService, 'project');
        $this->checkBudgetsForEntity($validDaysPerMonth, $recordDate, $now, $periodInsert, $rate, $duration, $project->getCustomer(), $this->customerStatisticService, 'customer');
    }

    /**
     * @param array<string, int> $validDaysPerMonth
     * @param DateTimeImmutable $recordDate
     * @param DateTime $now
     * @param PeriodInsertEntity $periodInsert
     * @param float $rate
     * @param int $duration
     * @param \App\Entity\Customer|\App\Entity\Project|\App\Entity\Activity $entity
     * @param CustomerStatisticService|ProjectStatisticService|ActivityStatisticService $statisticService
     * @param string $type
     */
    private function checkBudgetsForEntity(array $validDaysPerMonth, DateTimeImmutable $recordDate, DateTime $now, PeriodInsertEntity $periodInsert, float $rate, int $duration, mixed $entity, mixed $statisticService, string $type): void
    {
        if (null === $entity) {
            return;
        }

        if (!$entity->hasBudgets()) {
            return;
        }

        if ($entity->isMonthlyBudget()) {
            // check budget for each month of the period insert
            foreach ($validDaysPerMonth as $month => $validDaysInMonth) {
                $stat = $statisticService->getBudgetStatisticModel($entity, $recordDate);
                $this->checkBudgets($stat, $periodInsert, $rate, $duration, $validDaysInMonth, $type, $month);

                $recordDate->modify('+1 month');
            }
        } else {
            $stat = $statisticService->getBudgetStatisticModel($entity, $now);
            $this->checkBudgets($stat, $periodInsert, $rate, $duration, \count($periodInsert->getValidDays()), $type);
        }
    }

    /**
     * @param BudgetStatisticModel $stat
     * @param PeriodInsertEntity $periodInsert
     * @param float $rate
     * @param int $duration
     * @param int $validDays
     * @param string $field
     * @param string $month
     */
    private function checkBudgets(BudgetStatisticModel $stat, PeriodInsertEntity $periodInsert, float $rate, int $duration, int $validDays, string $field, string $month = ''): void
    {
        $fullRate = $stat->getBudgetSpent() + $rate * $validDays;

        if ($stat->hasBudget() && $fullRate > $stat->getBudget()) {
            $this->addBudgetViolationMessage($periodInsert, $field, $month, $stat->getBudget(), $stat->getBudgetSpent(), $rate * $validDays);

            if (!$this->security->isGranted('budget_' . $field)) {
                return;
            }
        }

        $fullDuration = $stat->getTimeBudgetSpent() + $duration * $validDays;

        if ($stat->hasTimeBudget() && $fullDuration > $stat->getTimeBudget()) {
            $this->addTimeBudgetViolationMessage($field, $month, $stat->getTimeBudget(), $stat->getTimeBudgetSpent(), $duration * $validDays);
        }
    }

    /**
     * @param PeriodInsertEntity $periodInsert
     * @param string $field
     * @param string $month
     * @param float $budget
     * @param float $rate
     * @param float $insertRate
     */
    private function addBudgetViolationMessage(PeriodInsertEntity $periodInsert, string $field, string $month, float $budget, float $rate, float $insertRate): void
    {
        $helper = new LocaleFormatter($this->localeService, $periodInsert->getUser()?->getLocale() ?? 'en');
        $currency = $periodInsert->getProject()->getCustomer()->getCurrency();

        $this->addViolationMessage($field, $month, $budget, $rate, $insertRate, fn($value) => $helper->money($value, $currency));
    }

    /**
     * @param string $field
     * @param string $month
     * @param int $budget
     * @param int $duration
     * @param int $insertDuration
     */
    private function addTimeBudgetViolationMessage(string $field, string $month, int $budget, int $duration, int $insertDuration): void
    {
        $durationFormat = new Duration();

        $this->addViolationMessage($field, $month, $budget, $duration, $insertDuration, fn($value) => $durationFormat->format($value));
    }

    /**
     * @param string $field
     * @param string $month
     * @param float $budget
     * @param float $usedValue
     * @param float|int $insertValue
     * @param callable $formatter
     */
    private function addViolationMessage(string $field, string $month, float $budget, float $usedValue, float|int $insertValue, callable $formatter): void
    {
        $message = PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::BUDGET_USED_ERROR);
        if ($this->security->isGranted('budget_' . $field)) {
            $free = $budget - $usedValue;
            $free = max($free, 0);

            $used = $formatter($usedValue);
            $budget = $formatter($budget);
            $free = $formatter($free);
            $insert = $formatter($insertValue);

            $messageFormat = 'The budget is used up. Of the available %s, %s has been booked so far, %s can still be used. The selected period insert would use %s';
            $message = sprintf($messageFormat, $budget, $used, $free, $insert);
        }

        if ($month !== '') {
            $message .= ' in ' . $month;
        }
        $message .= '.';

        $this->context->buildViolation($message)
            ->atPath($field)
            ->setTranslationDomain('validators')
            ->setCode(PeriodInsertConstraint::BUDGET_USED_ERROR)
            ->addViolation();
    }
}
