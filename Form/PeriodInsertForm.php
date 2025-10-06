<?php

/*
 * This file is part of the PeriodInsertBundle.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PeriodInsertBundle\Form;

use App\Configuration\SystemConfiguration;
use App\Entity\Customer;
use App\Entity\Timesheet;
use App\Form\TimesheetEditForm;
use App\Form\Type\CustomerType;
use App\Form\Type\DateRangeType;
use App\Form\Type\DescriptionType;
use App\Form\Type\DurationType;
use App\Form\Type\MetaFieldsCollectionType;
use App\Form\Type\TagsType;
use App\Form\Type\TimePickerType;
use App\Form\Type\TimesheetBillableType;
use App\Form\Type\YesNoType;
use App\Repository\CustomerRepository;
use App\Repository\Query\CustomerFormTypeQuery;
use App\WorkingTime\WorkingTimeService;
use KimaiPlugin\PeriodInsertBundle\Entity\PeriodInsert;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

final class PeriodInsertForm extends TimesheetEditForm
{
    public function __construct(private readonly CustomerRepository $customers,
        private readonly SystemConfiguration $systemConfiguration,
        private readonly WorkingTimeService $workService
    )
    {
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array<string, string|bool|int|null|array<string, mixed>> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $activity = null;
        $project = null;
        $customer = null;
        $currency = false;
        $isNew = true;

        if (isset($options['data']) && $options['data'] instanceof PeriodInsert) {
            /** @var PeriodInsert $periodInsert */
            $periodInsert = $options['data'];

            $activity = $periodInsert->getActivity();
            $project = $periodInsert->getProject();
            $customer = $project?->getCustomer();

            if (null === $project && null !== $activity) {
                $project = $activity->getProject();
            }

            if (null !== $customer) {
                $currency = $customer->getCurrency();
            }
        }

        $this->addUser($builder, $options);
        $this->addDateRange($builder, $options);

        if ($options['allow_begin_datetime']) {
            $this->addBeginTime($builder, $options);
        }
        
        $this->addDuration($builder, $options, !$options['allow_begin_datetime'], $isNew);

        $query = new CustomerFormTypeQuery();
        $query->setUser($options['user']); // @phpstan-ignore-line
        $qb = $this->customers->getQueryBuilderForFormType($query);
        /** @var Customer[] $customers */
        $customers = $qb->getQuery()->getResult();
        $customerCount = \count($customers);

        if ($this->showCustomer($options, $isNew, $customerCount)) {
            $builder->add('customer', CustomerType::class, [
                'choices' => $customers,
                'data' => $customer,
                'required' => false,
                'placeholder' => '',
                'mapped' => false,
                'project_enabled' => true,
            ]);
        }
        
        $this->addProject($builder, $isNew, $project, $customer);
        $this->addActivity($builder, $activity, $project);

        $builder->add('description', DescriptionType::class, ['required' => false]);
        $builder->add('tags', TagsType::class, ['required' => false]);
        $builder->add('metaFields', MetaFieldsCollectionType::class);

        $builder->add('monday', YesNoType::class)
            ->add('tuesday', YesNoType::class)
            ->add('wednesday', YesNoType::class)
            ->add('thursday', YesNoType::class)
            ->add('friday', YesNoType::class)
            ->add('saturday', YesNoType::class)
            ->add('sunday', YesNoType::class);
        
        $this->addRates($builder, $currency, $options);
        $this->addBillable($builder, $options);
        $this->addExported($builder, $options);

        // find dates that will be inserted by the period insert (by default, selected + no absences + working day)
        $builder->addEventListener(
            FormEvents::SUBMIT,
            function (FormEvent $event): void {
                /** @var PeriodInsert $periodInsert */
                $periodInsert = $event->getData();

                $includeAbsences = (bool) $this->systemConfiguration->find('periodinsert.include_absences');
                $currentMonth = '';
                $absences = [];

                $includeNonWorkdays = (bool) $this->systemConfiguration->find('periodinsert.include_nonworkdays');
                $contractModeCalculator = $this->workService->getContractMode($periodInsert->getUser())->getCalculator($periodInsert->getUser());
                
                for ($begin = clone $periodInsert->getBegin(), $end = $periodInsert->getEnd(); $begin <= $end; $begin->modify('+1 day')) {                    
                    if (!$includeAbsences && $currentMonth !== $begin->format('Y-m')) {
                        /** @var DateTime[] $absences */
                        $absences = [];
                        $month = $this->workService->getMonth($periodInsert->getUser(), $begin, $end);
                        $currentMonth = $begin->format('Y-m');

                        foreach ($month->getDays() as $day) {
                            if ($day->hasAddons()) {
                                $absences[] = $day->getDay()->format('Y-m-d');
                            }
                        }
                    }

                    if ($periodInsert->isDaySelected($begin) && ($includeAbsences || !in_array($begin->format('Y-m-d'), $absences)) && ($includeNonWorkdays || $contractModeCalculator->isWorkDay($begin))) {
                        $periodInsert->addValidDate($begin);
                    }
                }
            }
        );
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array<string, string|bool|int|null|array<string, mixed>> $options
     */
    protected function addDateRange(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('daterange', DateRangeType::class, [
            'required' => true,
            'allow_empty' => false,
            'timezone' => $options['timezone'],
        ]);

        $builder->addEventListener(
            FormEvents::SUBMIT,
            function (FormEvent $event): void {
                /** @var PeriodInsert $periodInsert */
                $periodInsert = $event->getData();
                $dateRange = $periodInsert->getDateRange();

                $hour = (int) $periodInsert->getBeginTime()->format('H');
                $minute = (int) $periodInsert->getBeginTime()->format('i');

                $dateRange->getBegin()->setTime($hour, $minute);
                $dateRange->getEnd()->setTime($hour, $minute);
                $dateRange->getEnd()->modify('+' . $periodInsert->getDuration() . ' seconds');
            }
        );
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array<string, string|bool|int|null|array<string, mixed>> $options
     */
    protected function addBeginTime(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('begin_time', TimePickerType::class, [
            'label' => 'Begin',
            'constraints' => [
                new NotBlank()
            ],
            'model_timezone' => $options['timezone'],
            'view_timezone' => $options['timezone'],
        ]);
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array<string, string|bool|int|null|array<string, mixed>> $options
     * @param bool $forceApply
     * @param bool $autofocus
     */
    protected function addDuration(FormBuilderInterface $builder, array $options, bool $forceApply = false, bool $autofocus = false): void
    {
        $durationOptions = [
            'required' => true,
            'attr' => [
                'placeholder' => '0:00',
            ],
        ];

        if ($autofocus) {
            $durationOptions['attr']['autofocus'] = 'autofocus';
        }

        $duration = $options['duration_minutes'];
        if ($duration !== null && (int) $duration > 0) {
            $durationOptions = array_merge($durationOptions, [
                'preset_minutes' => $duration
            ]);
        }

        $duration = $options['duration_hours'];
        if ($duration !== null && (int) $duration > 0) {
            $durationOptions = array_merge($durationOptions, [
                'preset_hours' => $duration,
            ]);
        }

        $builder->add('duration', DurationType::class, $durationOptions);

        if ($this->systemConfiguration->isBreakTimeEnabled()) {
            $builder->add('break', DurationType::class, ['label' => 'break', 'required' => false, 'icon' => 'break']);
        }
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array<string, string|bool|int|null|array<string, mixed>> $options
     */
    protected function addBillable(FormBuilderInterface $builder, array $options): void
    {
        if ($options['include_billable']) {
            $builder->add('billableMode', TimesheetBillableType::class, []);
        }

        $builder->addEventListener(
            FormEvents::SUBMIT,
            function (FormEvent $event): void {
                /** @var PeriodInsert $periodInsert */
                $periodInsert = $event->getData();

                switch ($periodInsert->getBillableMode()) {
                    case Timesheet::BILLABLE_NO:
                        $periodInsert->setBillable(false);
                        break;
                    case Timesheet::BILLABLE_YES:
                        $periodInsert->setBillable(true);
                        break;
                    case Timesheet::BILLABLE_AUTOMATIC:
                        $billable = true;
        
                        $activity = $periodInsert->getActivity();
                        if ($activity !== null && !$activity->isBillable()) {
                            $billable = false;
                        }
        
                        $project = $periodInsert->getProject();
                        if ($billable && $project !== null && !$project->isBillable()) {
                            $billable = false;
                        }
        
                        if ($billable && $project !== null) {
                            $customer = $project->getCustomer();
                            if ($customer !== null && !$customer->isBillable()) {
                                $billable = false;
                            }
                        }

                        $periodInsert->setBillable($billable);
                        break;
                }
            }
        );
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $maxMinutes = $this->systemConfiguration->getTimesheetLongRunningDuration();
        $maxHours = 10;
        if ($maxMinutes > 0) {
            $maxHours = (int) ($maxMinutes / 60);
        }
        
        $resolver->setDefaults([
            'data_class' => PeriodInsert::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'period_insert',
            'include_user' => false,
            'include_rate' => true,
            'include_billable' => true,
            'include_exported' => false,
            'method' => 'POST',
            'date_format' => null,
            'timezone' => date_default_timezone_get(),
            'customer' => true,
            'allow_begin_datetime' => true,
            'duration_minutes' => null,
            'duration_hours' => $maxHours,
            'attr' => [
                'data-form-event' => 'kimai.periodInsertUpdate',
                'data-msg-success' => 'action.update.success',
                'data-msg-error' => 'action.update.error',
            ],
        ]);
    }
}
