<?php

namespace App\Importers\Workshops;

use App\Actions\Workshops\CreateWorkshopAction;
use App\Actions\Workshops\UpdateWorkshopAction;
use App\Enums\SystemRole;
use App\Importers\BaseImporter;
use App\Models\User;
use App\Models\VehicleSystem;
use App\Models\Workshop;
use App\Rules\Workshops\ValidWorkshopSchedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as LaravelValidator;

final class WorkshopImporter extends BaseImporter
{
    public function __construct(
        private readonly CreateWorkshopAction $createWorkshopAction,
        private readonly UpdateWorkshopAction $updateWorkshopAction,
    ) {}

    /**
     * @return array<string, int>
     */
    protected function columnMap(): array
    {
        return [
            'manager_email' => 1,
            'name' => 2,
            'code' => 3,
            'is_active' => 4,
            'address' => 5,
            'city' => 6,
            'phone' => 7,
            'email' => 8,
            'vehicle_system_codes' => 9,
            'technician_emails' => 10,
            'weekly_schedule' => 11,
        ];
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $columnMap
     * @return array<string, mixed>
     */
    protected function payloadFromRow(array $row, array $columnMap): array
    {
        return [
            'manager_email' => $this->email($row[$columnMap['manager_email'] ?? 0] ?? null),
            'name' => $this->nullableString($row[$columnMap['name'] ?? 0] ?? null),
            'code' => $this->code($row[$columnMap['code'] ?? 0] ?? null),
            'is_active' => $this->boolean($row[$columnMap['is_active'] ?? 0] ?? null),
            'address' => $this->nullableString($row[$columnMap['address'] ?? 0] ?? null),
            'city' => $this->nullableString($row[$columnMap['city'] ?? 0] ?? null),
            'phone' => $this->nullableString($row[$columnMap['phone'] ?? 0] ?? null),
            'email' => $this->email($row[$columnMap['email'] ?? 0] ?? null),
            'vehicle_system_codes' => $this->codeList($row[$columnMap['vehicle_system_codes'] ?? 0] ?? null),
            'technician_emails' => $this->emailList($row[$columnMap['technician_emails'] ?? 0] ?? null),
            'weekly_schedule' => $this->schedule($row[$columnMap['weekly_schedule'] ?? 0] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function matchingRecord(array $payload): ?Model
    {
        if (! is_string($payload['code'] ?? null) || $payload['code'] === '') {
            return null;
        }

        return Workshop::query()
            ->where('code', $payload['code'])
            ->first();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(?Model $record): array
    {
        $workshop = $record instanceof Workshop ? $record : null;
        $workshopId = $workshop?->getKey();

        return [
            'manager_email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                function (string $attribute, mixed $value, mixed $fail) use ($workshop): void {
                    if (! is_string($value) || $value === '') {
                        return;
                    }

                    $manager = $this->managerForEmail($value);

                    if (! $manager instanceof User) {
                        $fail(__('api.validation.rules.manager_active_role'));

                        return;
                    }

                    $assignedWorkshopQuery = Workshop::query()
                        ->where('manager_user_id', $manager->getKey());

                    if ($workshop instanceof Workshop) {
                        $assignedWorkshopQuery->where('id', '<>', $workshop->getKey());
                    }

                    if ($assignedWorkshopQuery->exists()) {
                        $fail(__('api.validation.rules.manager_assigned_elsewhere'));
                    }
                },
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('workshops', 'name')->ignore($workshopId),
            ],
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('workshops', 'code')->ignore($workshopId),
            ],
            'is_active' => [
                'required',
                function (string $attribute, mixed $value, mixed $fail): void {
                    if (! is_bool($value)) {
                        $fail(__('validation.boolean', ['attribute' => $this->attributes()[$attribute] ?? $attribute]));
                    }
                },
            ],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'vehicle_system_codes' => ['required', 'array', 'min:1'],
            'vehicle_system_codes.*' => [
                'required',
                'string',
                'distinct',
                Rule::exists('vehicle_systems', 'code'),
            ],
            'technician_emails' => ['present', 'array'],
            'technician_emails.*' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                'distinct',
                function (string $attribute, mixed $value, mixed $fail) use ($workshopId): void {
                    if (! is_string($value) || $value === '') {
                        return;
                    }

                    $technician = $this->technicianForEmail($value);

                    if (! $technician instanceof User) {
                        $fail(__('api.validation.rules.technician_active_role'));

                        return;
                    }

                    if ($technician->workshop_id !== null && (string) $technician->workshop_id !== (string) $workshopId) {
                        $fail(__('api.validation.rules.technician_assigned_elsewhere'));
                    }
                },
            ],
            'weekly_schedule' => ['required', 'array', 'min:1'],
            'weekly_schedule.*' => ['required', 'array'],
            'weekly_schedule.*.opens_at' => ['required', 'date_format:H:i'],
            'weekly_schedule.*.closes_at' => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function attributes(): array
    {
        return [
            'manager_email' => __('exports.workshops.columns.manager_email'),
            'name' => __('exports.workshops.columns.name'),
            'code' => __('exports.workshops.columns.code'),
            'is_active' => __('exports.workshops.columns.is_active'),
            'address' => __('exports.workshops.columns.address'),
            'city' => __('exports.workshops.columns.city'),
            'phone' => __('exports.workshops.columns.phone'),
            'email' => __('exports.workshops.columns.email'),
            'vehicle_system_codes' => __('exports.workshops.columns.vehicle_system_codes'),
            'technician_emails' => __('exports.workshops.columns.technician_emails'),
            'weekly_schedule' => __('exports.workshops.columns.weekly_schedule'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function withValidator(LaravelValidator $validator, array $payload, ?Model $record): void
    {
        $validator->after(function (LaravelValidator $validator) use ($payload): void {
            app(ValidWorkshopSchedule::class)->validate($validator, $payload['weekly_schedule'] ?? null);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return self::CREATED|self::UPDATED
     */
    protected function persist(array $data, ?Model $record): string
    {
        $workshopData = $this->workshopData($data);

        if ($record instanceof Workshop) {
            $this->updateWorkshopAction->execute($record, $workshopData);

            return self::UPDATED;
        }

        $this->createWorkshopAction->execute($workshopData);

        return self::CREATED;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function workshopData(array $data): array
    {
        /** @var User $manager */
        $manager = $this->managerForEmail((string) $data['manager_email']);

        /** @var array<int, string> $vehicleSystemCodes */
        $vehicleSystemCodes = $data['vehicle_system_codes'];
        /** @var array<int, string> $technicianEmails */
        $technicianEmails = $data['technician_emails'] ?? [];

        return [
            'manager_user_id' => $manager->getKey(),
            'name' => $data['name'],
            'code' => $data['code'],
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'weekly_schedule' => $data['weekly_schedule'],
            'vehicle_system_ids' => $this->vehicleSystemIds($vehicleSystemCodes),
            'technician_user_ids' => $this->technicianIds($technicianEmails),
            'is_active' => $data['is_active'],
        ];
    }

    private function managerForEmail(string $email): ?User
    {
        return User::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->whereHas('roles', function ($query): void {
                $query->where('name', SystemRole::WorkshopManager->value);
            })
            ->first();
    }

    private function technicianForEmail(string $email): ?User
    {
        return User::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->whereHas('roles', function ($query): void {
                $query->where('name', SystemRole::Technician->value);
            })
            ->first();
    }

    /**
     * @param  array<int, string>  $codes
     * @return array<int, int>
     */
    private function vehicleSystemIds(array $codes): array
    {
        $idsByCode = VehicleSystem::query()
            ->whereIn('code', $codes)
            ->pluck('id', 'code');

        return array_values(array_filter(array_map(
            static fn (string $code): ?int => $idsByCode[$code] ?? null,
            $codes,
        )));
    }

    /**
     * @param  array<int, string>  $emails
     * @return array<int, int>
     */
    private function technicianIds(array $emails): array
    {
        if ($emails === []) {
            return [];
        }

        $idsByEmail = User::query()
            ->whereIn('email', $emails)
            ->pluck('id', 'email');

        return array_values(array_filter(array_map(
            static fn (string $email): ?int => $idsByEmail[$email] ?? null,
            $emails,
        )));
    }

    private function schedule(mixed $value): mixed
    {
        if (is_array($value)) {
            return $value;
        }

        $string = $this->nullableString($value);

        if ($string === null) {
            return null;
        }

        $decoded = json_decode($string, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return $string;
        }

        $normalized = [];

        foreach ($decoded as $day => $hours) {
            $normalized[Str::lower((string) $day)] = $hours;
        }

        return $normalized;
    }
}
