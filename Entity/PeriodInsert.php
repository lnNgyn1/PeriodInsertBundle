<?php

/*
 * This file is part of the PeriodInsertBundle.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PeriodInsertBundle\Entity;

class PeriodInsert
{

    private $user;
    private $beginToEnd;
    private $beginTime;
    private $project;
    private $activity;
    private $durationPerDay;
    private $description;
    private $tags;
    private $fixedRate;
    private $hourlyRate;
    private $exported;
    private $billableMode;
    private $monday;
    private $tuesday;
    private $wednesday;
    private $thursday;
    private $friday;
    private $saturday;
    private $sunday;

    /**
     * @return mixed
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param mixed $user
     */
    public function setUser($user): void
    {
        $this->user = $user;
    }

    /**
     * @return mixed
     */
    public function getBeginToEnd()
    {
        return $this->beginToEnd;
    }

    /**
     * @param mixed $beginToEnd
     */
    public function setBeginToEnd($beginToEnd): void
    {
        $this->beginToEnd = $beginToEnd;
    }

    /**
     * @return mixed
     */
    public function getBegin()
    {
        return $this->getBeginToEnd()->getBegin();
    }

    /**
     * @return mixed
     */
    public function getEnd()
    {
        return $this->getBeginToEnd()->getEnd();
    }

    /**
     * @return mixed
     */
    public function getBeginTime()
    {
        return $this->beginTime;
    }

    /**
     * @param mixed $beginTime
     */
    public function setBeginTime($beginTime): void
    {
        $this->beginTime = $beginTime;
    }
    
    /**
     * @return mixed
     */
    public function getProject()
    {
        return $this->project;
    }

    /**
     * @param mixed $project
     */
    public function setProject($project): void
    {
        $this->project = $project;
    }

    /**
     * @return mixed
     */
    public function getActivity()
    {
        return $this->activity;
    }

    /**
     * @param mixed $activity
     */
    public function setActivity($activity): void
    {
        $this->activity = $activity;
    }

    /**
     * @return mixed
     */
    public function getDurationPerDay()
    {
        return $this->durationPerDay;
    }

    /**
     * @param mixed $durationPerDay
     */
    public function setDurationPerDay($durationPerDay)
    {
        $this->durationPerDay = $durationPerDay;
    }

    /**
     * @return mixed
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param mixed $description
     */
    public function setDescription($description): void
    {
        $this->description = $description;
    }

    /**
     * @return mixed
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * @param mixed $tags
     */
    public function setTags($tags): void
    {
        $this->tags = $tags;
    }

    /**
     * @return mixed $fixedRate
     */
    public function getFixedRate()
    {
        return $this->fixedRate;
    }

    /**
     * @param mixed $fixedRate
     */
    public function setFixedRate($fixedRate): void
    {
        $this->fixedRate = $fixedRate;
    }

    /**
     * @return mixed $hourlyRate
     */
    public function getHourlyRate()
    {
        return $this->hourlyRate;
    }

    /**
     * @param mixed $hourlyRate
     */
    public function setHourlyRate($hourlyRate): void
    {
        $this->hourlyRate = $hourlyRate;
    }

    /**
     * @return mixed $billableMode
     */
    public function getBillableMode()
    {
        return $this->billableMode;
    }

    /**
     * @param mixed $billableMode
     */
    public function setBillableMode($billableMode): void
    {
        $this->billableMode = $billableMode;
    }

    /**
     * @return mixed $exported
     */
    public function getExported()
    {
        return $this->exported;
    }

    /**
     * @param mixed $exported
     */
    public function setExported($exported): void
    {
        $this->exported = $exported;
    }

    /**
     * @return mixed $monday
     */
    public function getMonday()
    {
        return $this->monday;
    }

    /**
     * @param mixed $monday
     */
    public function setMonday($monday): void
    {
        $this->monday = $monday;
    }

    /**
     * @return mixed $tuesday
     */
    public function getTuesday()
    {
        return $this->tuesday;
    }

    /**
     * @param mixed $tuesday
     */
    public function setTuesday($tuesday): void
    {
        $this->tuesday = $tuesday;
    }

    /**
     * @return mixed $wednesday
     */
    public function getWednesday()
    {
        return $this->wednesday;
    }

    /**
     * @param mixed $wednesday
     */
    public function setWednesday($wednesday): void
    {
        $this->wednesday = $wednesday;
    }

    /**
     * @return mixed $thursday
     */
    public function getThursday()
    {
        return $this->thursday;
    }

    /**
     * @param mixed $thursday
     */
    public function setThursday($thursday): void
    {
        $this->thursday = $thursday;
    }

    /**
     * @return mixed $friday
     */
    public function getFriday()
    {
        return $this->friday;
    }

    /**
     * @param mixed $friday
     */
    public function setFriday($friday): void
    {
        $this->friday = $friday;
    }

    /**
     * @return mixed $saturday
     */
    public function getSaturday()
    {
        return $this->saturday;
    }

    /**
     * @param mixed $saturday
     */
    public function setSaturday($saturday): void
    {
        $this->saturday = $saturday;
    }

    /**
     * @return mixed $sunday
     */
    public function getSunday()
    {
        return $this->sunday;
    }

    /**
     * @param mixed $sunday
     */
    public function setSunday($sunday): void
    {
        $this->sunday = $sunday;
    }
}
