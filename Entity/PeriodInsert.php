<?php

/*
 * This file is part of the PeriodInsertBundle.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PeriodInsertBundle\Entity;

use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\Tag;
use App\Entity\Timesheet;
use App\Entity\TimesheetMeta;
use App\Entity\User;
use App\Form\Model\DateRange;
use DateTime;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use KimaiPlugin\PeriodInsertBundle\Validator\Constraints as Constraints;

#[Constraints\PeriodInsert]
class PeriodInsert
{
    private ?User $user = null;
    private ?DateRange $dateRange = null;
    private ?DateTime $beginTime = null;
    private ?int $duration = null;
    private ?int $break = null;
    private ?Project $project = null;
    private ?Activity $activity = null;
    private ?string $description = null;
    /**
     * @var Collection<Tag>
     */
    private Collection $tags;
    /**
     * Meta fields registered with the timesheet
     *
     * @var Collection<TimesheetMeta>
     */
    private Collection $meta;
    /**
     * @var bool[]
     */
    private array $days = [true, true, true, true, true, true, true];
    private ?float $fixedRate = null;
    private ?float $hourlyRate = null;
    private ?float $internalRate = null;
    private bool $billable = true;
    private ?string $billableMode = Timesheet::BILLABLE_AUTOMATIC;
    private bool $exported = false;
    /**
     * @var DateTimeImmutable[]
     */
    private array $validDates = [];

    public function __construct()
    {
        $this->tags = new ArrayCollection();
        $this->meta = new ArrayCollection();
    }

    /**
     * @return User|null
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @param User|null $user
     * @return PeriodInsert
     */
    public function setUser(?User $user): PeriodInsert
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return DateRange|null
     */
    public function getDateRange(): ?DateRange
    {
        return $this->dateRange;
    }

    /**
     * @param DateRange|null $dateRange
     * @return PeriodInsert
     */
    public function setDateRange(?DateRange $dateRange): PeriodInsert
    {
        $this->dateRange = $dateRange;

        return $this;
    }

    /**
     * @return DateTime|null
     */
    public function getBegin(): ?DateTime
    {
        return $this->dateRange?->getBegin();
    }

    /**
     * @return DateTime|null
     */
    public function getEnd(): ?DateTime
    {
        return $this->dateRange?->getEnd();
    }

    /**
     * @return DateTime|null
     */
    public function getBeginTime(): ?DateTime
    {
        return $this->beginTime;
    }

    /**
     * @param DateTime|null $beginTime
     * @return PeriodInsert
     */
    public function setBeginTime(?DateTime $beginTime): PeriodInsert
    {
        $this->beginTime = $beginTime;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getDuration(): ?int
    {
        return $this->duration;
    }

    /**
     * @param int|null $duration
     * @return PeriodInsert
     */
    public function setDuration(?int $duration): PeriodInsert
    {
        $this->duration = $duration;

        return $this;
    }

    /**
     * @return int
     */
    public function getBreak(): int
    {
        return $this->break ?? 0;
    }

    /**
     * @param int|null $break
     * @return PeriodInsert
     */
    public function setBreak(?int $break): PeriodInsert
    {
        $this->break = $break;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getCalculatedDuration(): ?int
    {
        if (null !== $this->getDuration()) {
            return $this->getDuration() - $this->getBreak();
        }
        
        return null;
    }
    
    /**
     * @return Project|null
     */
    public function getProject(): ?Project
    {
        return $this->project;
    }

    /**
     * @param Project|null $project
     * @return PeriodInsert
     */
    public function setProject(?Project $project): PeriodInsert
    {
        $this->project = $project;

        return $this;
    }

    /**
     * @return Activity|null
     */
    public function getActivity(): ?Activity
    {
        return $this->activity;
    }

    /**
     * @param Activity|null $activity
     * @return PeriodInsert
     */
    public function setActivity(?Activity $activity): PeriodInsert
    {
        $this->activity = $activity;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param string|null $description
     * @return PeriodInsert
     */
    public function setDescription(?string $description): PeriodInsert
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return Collection<Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    /**
     * @param Collection<Tag> $tags
     * @return PeriodInsert
     */
    public function setTags(Collection $tags): PeriodInsert
    {
        $this->tags = $tags;

        return $this;
    }

    /**
     * @return Collection<TimesheetMeta>
     */
    public function getMetaFields(): Collection
    {
        return $this->meta;
    }

    /**
     * @param Collection<TimesheetMeta> $meta
     * @return PeriodInsert
     */
    public function setMetaFields(Collection $meta): PeriodInsert
    {
        $this->meta = $meta;

        return $this;
    }

    /**
     * @param DateTime $day
     * @return bool
     */
    public function isDaySelected(DateTime $day): bool
    {
        return $this->days[(int) $day->format('w')];
    }

    /**
     * @return bool
     */
    public function getMonday(): bool
    {
        return $this->days[1];
    }

    /**
     * @param bool $monday
     * @return PeriodInsert
     */
    public function setMonday(bool $monday): PeriodInsert
    {
        $this->days[1] = $monday;

        return $this;
    }

    /**
     * @return bool
     */
    public function getTuesday(): bool
    {
        return $this->days[2];
    }

    /**
     * @param bool $tuesday
     * @return PeriodInsert
     */
    public function setTuesday(bool $tuesday): PeriodInsert
    {
        $this->days[2] = $tuesday;

        return $this;
    }

    /**
     * @return bool
     */
    public function getWednesday(): bool
    {
        return $this->days[3];
    }

    /**
     * @param bool $wednesday
     * @return PeriodInsert
     */
    public function setWednesday(bool $wednesday): PeriodInsert
    {
        $this->days[3] = $wednesday;

        return $this;
    }

    /**
     * @return bool
     */
    public function getThursday(): bool
    {
        return $this->days[4];
    }

    /**
     * @param bool $thursday
     * @return PeriodInsert
     */
    public function setThursday(bool $thursday): PeriodInsert
    {
        $this->days[4] = $thursday;

        return $this;
    }

    /**
     * @return bool
     */
    public function getFriday(): bool
    {
        return $this->days[5];
    }

    /**
     * @param bool $friday
     * @return PeriodInsert
     */
    public function setFriday(bool $friday): PeriodInsert
    {
        $this->days[5] = $friday;

        return $this;
    }

    /**
     * @return bool
     */
    public function getSaturday(): bool
    {
        return $this->days[6];
    }

    /**
     * @param bool $saturday
     * @return PeriodInsert
     */
    public function setSaturday(bool $saturday): PeriodInsert
    {
        $this->days[6] = $saturday;

        return $this;
    }

    /**
     * @return bool
     */
    public function getSunday(): bool
    {
        return $this->days[0];
    }

    /**
     * @param bool $sunday
     * @return PeriodInsert
     */
    public function setSunday(bool $sunday): PeriodInsert
    {
        $this->days[0] = $sunday;

        return $this;
    }

    /**
     * @return float|null
     */
    public function getFixedRate(): ?float
    {
        return $this->fixedRate;
    }

    /**
     * @param float|null $fixedRate
     * @return PeriodInsert
     */
    public function setFixedRate(?float $fixedRate): PeriodInsert
    {
        $this->fixedRate = $fixedRate;

        return $this;
    }

    /**
     * @return float|null
     */
    public function getHourlyRate(): ?float
    {
        return $this->hourlyRate;
    }

    /**
     * @param float|null $hourlyRate
     * @return PeriodInsert
     */
    public function setHourlyRate(?float $hourlyRate): PeriodInsert
    {
        $this->hourlyRate = $hourlyRate;

        return $this;
    }

    /**
     * @return float|null
     */
    public function getInternalRate(): ?float
    {
        return $this->internalRate;
    }

    /**
     * @param float|null $internalRate
     * @return PeriodInsert
     */
    public function setInternalRate(?float $internalRate): PeriodInsert
    {
        $this->internalRate = $internalRate;

        return $this;
    }

    /**
     * @return bool
     */
    public function isBillable(): bool
    {
        return $this->billable;
    }

    /**
     * @param bool $billable
     * @return PeriodInsert
     */
    public function setBillable(bool $billable): PeriodInsert
    {
        $this->billable = $billable;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getBillableMode(): ?string
    {
        return $this->billableMode;
    }

    /**
     * @param string|null $billableMode
     */
    public function setBillableMode(?string $billableMode): void
    {
        $this->billableMode = $billableMode;
    }

    /**
     * @return bool
     */
    public function getExported(): bool
    {
        return $this->exported;
    }

    /**
     * @param bool $exported
     * @return PeriodInsert
     */
    public function setExported(bool $exported): PeriodInsert
    {
        $this->exported = $exported;

        return $this;
    }

    /**
     * @return DateTimeImmutable[]
     */
    public function getValidDates(): array
    {
        return $this->validDates;
    }

    /**
     * @param DateTime
     * @return PeriodInsert
     */
    public function addValidDate(DateTime $date): PeriodInsert
    {
        if (in_array($date, $this->validDates)) {
            return $this;
        }
        $this->validDates[] = DateTimeImmutable::createFromMutable($date);

        return $this;
    }
}
