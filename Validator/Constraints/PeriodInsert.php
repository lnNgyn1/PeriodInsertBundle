<?php

/*
 * This file is part of the PeriodInsertBundle.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PeriodInsertBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class PeriodInsert extends Constraint
{
    public const MISSING_TIME_RANGE_ERROR = 'kimai-period-insert-bundle-01';
    public const MISSING_BEGIN_TIME_ERROR = 'kimai-period-insert-bundle-02';
    public const MISSING_DURATION_ERROR = 'kimai-period-insert-bundle-03';
    public const NEGATIVE_DURATION_ERROR = 'kimai-period-insert-bundle-04';
    public const INVALID_DURATION_ERROR = 'kimai-period-insert-bundle-05';
    public const ZERO_DURATION_ERROR = 'kimai-period-insert-bundle-06';
    public const NEGATIVE_BREAK_ERROR = 'kimai-period-insert-bundle-07';
    public const INVALID_BREAK_ERROR = 'kimai-period-insert-bundle-08';
    public const BREAK_DURATION_ERROR = 'kimai-period-insert-bundle-09';
    public const MISSING_ACTIVITY_ERROR = 'kimai-period-insert-bundle-10';
    public const DISABLED_ACTIVITY_ERROR = 'kimai-period-insert-bundle-11';
    public const MISSING_PROJECT_ERROR = 'kimai-period-insert-bundle-12';
    public const DISABLED_PROJECT_ERROR = 'kimai-period-insert-bundle-13';
    public const ACTIVITY_PROJECT_MISMATCH_ERROR = 'kimai-period-insert-bundle-14';
    public const PROJECT_DISALLOWS_GLOBAL_ACTIVITY_ERROR = 'kimai-period-insert-bundle-15';
    public const DISABLED_CUSTOMER_ERROR = 'kimai-period-insert-bundle-16';
    public const MISSING_DAY_ERROR = 'kimai-period-insert-bundle-17';
    public const PROJECT_NOT_STARTED_ERROR = 'kimai-period-insert-bundle-18';
    public const PROJECT_ALREADY_ENDED_ERROR = 'kimai-period-insert-bundle-19';
    public const TIME_RANGE_IN_FUTURE_ERROR = 'kimai-period-insert-bundle-20';
    public const BEGIN_IN_FUTURE_ERROR = 'kimai-period-insert-bundle-21';
    public const END_IN_FUTURE_ERROR = 'kimai-period-insert-bundle-22';
    public const RECORD_OVERLAPPING_ERROR = 'kimai-period-insert-bundle-23';
    public const BUDGET_USED_ERROR = 'kimai-period-insert-bundle-24';

    protected const ERROR_NAMES = [
        self::MISSING_TIME_RANGE_ERROR => 'You must submit a time range.',
        self::MISSING_BEGIN_TIME_ERROR => 'You must submit a begin time.',
        self::MISSING_DURATION_ERROR => 'You must submit a duration.',
        self::NEGATIVE_DURATION_ERROR => 'A negative duration is not allowed.',
        self::INVALID_DURATION_ERROR => 'Duration cannot be be longer than 24 hours.',
        self::ZERO_DURATION_ERROR => 'An empty duration is not allowed.',
        self::NEGATIVE_BREAK_ERROR => 'A negative break is not allowed.',
        self::INVALID_BREAK_ERROR => 'Break cannot be be longer than 24 hours.',
        self::BREAK_DURATION_ERROR => 'Break must be shorter than the duration.',
        self::MISSING_ACTIVITY_ERROR => 'An activity needs to be selected.',
        self::DISABLED_ACTIVITY_ERROR => 'Cannot start a disabled activity.',
        self::MISSING_PROJECT_ERROR => 'A project needs to be selected.',
        self::DISABLED_PROJECT_ERROR => 'Cannot start a disabled project.',
        self::ACTIVITY_PROJECT_MISMATCH_ERROR => 'Project mismatch, project specific activity and period insert project are different.',
        self::PROJECT_DISALLOWS_GLOBAL_ACTIVITY_ERROR => 'Global activities are forbidden for the selected project.',
        self::DISABLED_CUSTOMER_ERROR => 'Cannot start a disabled customer.',
        self::MISSING_DAY_ERROR => 'Could not find a valid day in the selected time range. Check the time range for absences and working days.',
        self::PROJECT_NOT_STARTED_ERROR => 'The project has not started during the selected time range.',
        self::PROJECT_ALREADY_ENDED_ERROR => 'The project is finished during the selected time range.',
        self::TIME_RANGE_IN_FUTURE_ERROR => 'The time range cannot be in the future.',
        self::BEGIN_IN_FUTURE_ERROR => 'The begin time cannot be in the future.',
        self::END_IN_FUTURE_ERROR => 'The end time cannot be in the future.',
        self::RECORD_OVERLAPPING_ERROR => 'You already have an entry on ',
        self::BUDGET_USED_ERROR => 'Sorry, the budget is used up',
    ];

    public string $message = 'This period insert has invalid settings.';

    /**
     * @return string
     */
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
