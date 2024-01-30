<?php

/*
 * This file is part of the PeriodInsertBundle.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PeriodInsertBundle\Form;

use App\Configuration\SystemConfiguration;
use App\Entity\Activity;
use App\Entity\Customer;
use App\Entity\Project;
use App\Form\Type\ActivityType;
use App\Form\Type\CustomerType;
use App\Form\Type\DateRangeType;
use App\Form\Type\DescriptionType;
use App\Form\Type\DurationType;
use App\Form\Type\FixedRateType;
use App\Form\Type\HourlyRateType;
use App\Form\Type\ProjectType;
use App\Form\Type\TagsType;
use App\Form\Type\TimesheetBillableType;
use App\Form\Type\UserType;
use App\Form\Type\YesNoType;
use App\Repository\CustomerRepository;
use App\Repository\ProjectRepository;
use App\Repository\Query\ProjectFormTypeQuery;
use KimaiPlugin\PeriodInsertBundle\Entity\PeriodInsert;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PeriodInsertType extends AbstractType
{
    public function __construct(private CustomerRepository $customers, private SystemConfiguration $systemConfiguration)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $activity = null;
        $project = null;
        $customer = null;
        $currency = false;
        $customerCount = $this->customers->countCustomer(true);
        $isNew = true;

        $this->addUser($builder, $options);
        $this->addDateRange($builder, $options, false);
        
        if ($this->showCustomer($options, $isNew, $customerCount)) {
            $this->addCustomer($builder, $customer);
        }

        $allowCreate = (bool) $this->systemConfiguration->find('activity.allow_inline_create');

        $this->addProject($builder, $isNew, $project, $customer);
        $this->addActivity($builder, $activity, $project, [
            'allow_create' => $allowCreate && $options['create_activity'],
        ]);

        $this->addDuration($builder, $options, $isNew);
        $this->addDescription($builder, $isNew);
        $this->addTags($builder);
        $this->addRates($builder, $currency, $options);
        $this->addBillable($builder, $options);
        $this->addExported($builder, $options);
    }

    protected function showCustomer(array $options, bool $isNew, int $customerCount): bool
    {
        if (!$isNew && $options['customer']) {
            return true;
        }

        if ($customerCount < 2) {
            return false;
        }

        if (!$options['customer']) {
            return false;
        }

        return true;
    }

    protected function addUser(FormBuilderInterface $builder, array $options)
    {
        if (!$options['include_user']) {
            return;
        }

        $builder->add('user', UserType::class, [
            'required' => true,
        ]);
    }

    protected function addDateRange(FormBuilderInterface $builder, array $options, bool $allowEmpty = true): void
    {
        $params = [
            'required' => !$allowEmpty,
            'allow_empty' => $allowEmpty,
            'label' => 'Time range',
        ];

        if (\array_key_exists('timezone', $options)) {
            $params['timezone'] = $options['timezone'];
        }

        $builder->add('beginToEnd', DateRangeType::class, $params);
    }

    protected function addCustomer(FormBuilderInterface $builder, ?Customer $customer = null): void
    {
        $builder->add('customer', CustomerType::class, [
            'query_builder_for_user' => true,
            'customers' => $customer,
            'data' => $customer,
            'required' => false,
            'placeholder' => '',
            'mapped' => false,
            'project_enabled' => true,
        ]);
    }

    protected function addProject(FormBuilderInterface $builder, bool $isNew, ?Project $project = null, ?Customer $customer = null, array $options = []): void
    {
        $options = array_merge([
            'placeholder' => '',
            'activity_enabled' => true,
            'query_builder_for_user' => true,
            'join_customer' => true
        ], $options);

        $builder->add('project', ProjectType::class, array_merge($options, [
            'projects' => $project,
            'customers' => $customer,
        ]));

        // replaces the project select after submission, to make sure only projects for the selected customer are displayed
        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) use ($builder, $project, $customer, $isNew, $options) {
                /** @var array<string, mixed> $data */
                $data = $event->getData();
                $customer = \array_key_exists('customer', $data) && $data['customer'] !== '' ? $data['customer'] : null;
                $project = \array_key_exists('project', $data) && $data['project'] !== '' ? $data['project'] : $project;

                $event->getForm()->add('project', ProjectType::class, array_merge($options, [
                    'group_by' => null,
                    'query_builder' => function (ProjectRepository $repo) use ($builder, $project, $customer, $isNew) {
                        // is there a better way to prevent starting a record with a hidden project ?
                        $project = \is_string($project) ? (int) $project : $project;
                        $customer = \is_string($customer) ? (int) $customer : $customer;
                        if ($isNew && \is_int($project)) {
                            /** @var Project $project */
                            $project = $repo->find($project);
                            if ($project === null) {
                                throw new \Exception('Unknown project');
                            }
                            if (!$project->getCustomer()->isVisible()) {
                                $customer = null;
                                $project = null;
                            } elseif (!$project->isVisible()) {
                                $project = null;
                            }
                        }

                        if ($project !== null && !\is_int($project) && !($project instanceof Project)) {
                            throw new \InvalidArgumentException('Project type needs a project object or an ID');
                        }

                        if ($customer !== null && !\is_int($customer) && !($customer instanceof Customer)) {
                            throw new \InvalidArgumentException('Project type needs a customer object or an ID');
                        }

                        $query = new ProjectFormTypeQuery($project, $customer);
                        $query->setUser($builder->getOption('user'));
                        $query->setWithCustomer(true);

                        return $repo->getQueryBuilderForFormType($query);
                    },
                ]));
            }
        );
    }

    protected function addActivity(FormBuilderInterface $builder, ?Activity $activity = null, ?Project $project = null, array $options = []): void
    {
        $options = array_merge(['placeholder' => '', 'query_builder_for_user' => true], $options);

        $options['projects'] = $project;
        $options['activities'] = $activity;

        $builder->add('activity', ActivityType::class, $options);

        // replaces the activity select after submission, to make sure only activities for the selected project are displayed
        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) use ($options) {
                /** @var array<string, mixed> $data */
                $data = $event->getData();

                if (!\array_key_exists('project', $data) || $data['project'] === '' || $data['project'] === null) {
                    return;
                }

                $options['projects'] = $data['project'];

                $event->getForm()->add('activity', ActivityType::class, $options);
            }
        );
    }

    protected function addDuration(FormBuilderInterface $builder, array $options, bool $autofocus = false): void
    {
        $durationOptions = [
            'required' => false,
            //'toggle' => true,
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

        $builder->add('durationPerDay', DurationType::class, $durationOptions);
    }

    protected function addDescription(FormBuilderInterface $builder, bool $isNew): void
    {
        $descriptionOptions = ['required' => false];
        if (!$isNew) {
            $descriptionOptions['attr'] = ['autofocus' => 'autofocus'];
        }
        $builder->add('description', DescriptionType::class, $descriptionOptions);
    }

    protected function addTags(FormBuilderInterface $builder): void
    {
        $builder->add('tags', TagsType::class, [
            'required' => false,
        ]);
    }

    protected function addRates(FormBuilderInterface $builder, $currency, array $options): void
    {
        if (!$options['include_rate']) {
            return;
        }

        $builder
            ->add('fixedRate', FixedRateType::class, [
                'currency' => $currency,
            ])
            ->add('hourlyRate', HourlyRateType::class, [
                'currency' => $currency,
                'attr' => [
                    'placeholder' => '0.00',
                ],
            ]);
    }

    protected function addBillable(FormBuilderInterface $builder, array $options): void
    {
        if ($options['include_billable']) {
            $builder->add('billableMode', TimesheetBillableType::class, []);
        }
    }

    protected function addExported(FormBuilderInterface $builder, array $options): void
    {
        if (!$options['include_exported']) {
            return;
        }

        $builder->add('exported', YesNoType::class, [
            'label' => 'exported'
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $maxMinutes = $this->systemConfiguration->getTimesheetLongRunningDuration();
        $maxHours = 8;
        if ($maxMinutes > 0) {
            $maxHours = (int) ($maxMinutes / 60);
        }
        
        $resolver->setDefaults([
            'data_class' => PeriodInsert::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'period_insert',
            'method' => 'POST',
            'include_user' => false,
            'include_rate' => true,
            'include_billable' => true,
            'include_exported' => false,
            'create_activity' => false,
            'duration_minutes' => null,
            'duration_hours' => $maxHours,
            'timezone' => date_default_timezone_get(),
            'customer' => false,
            'attr' => [
                'data-form-event' => 'kimai.timesheetUpdate',
                'data-msg-success' => 'action.update.success',
                'data-msg-error' => 'action.update.error',
            ],
        ]);
    }
}
