<?php

namespace App\Http\Requests\Contractor;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The web job form: job details, the client (existing or new) and the milestone schedule, in ringgit. */
class JobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'client_id' => ['nullable', 'required_without:client_name', Rule::exists('clients', 'id')->where('company_id', $companyId)],
            'client_name' => ['nullable', 'required_without:client_id', 'string', 'max:255'],
            'client_phone' => ['nullable', 'required_without:client_id', 'string', 'max:30'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'site_address' => ['required', 'string', 'max:500'],
            'quote' => ['required', 'decimal:0,2', 'min:1', 'max:99999999'],
            'start_date' => ['nullable', 'date'],
            'target_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'milestones' => ['required', 'array', 'min:1', 'max:30'],
            'milestones.*.name' => ['required', 'string', 'max:255'],
            'milestones.*.amount' => ['required', 'decimal:0,2', 'min:0'],
            'milestones.*.due_date' => ['nullable', 'date'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'client_id' => __('client'),
            'client_name' => __('client name'),
            'client_phone' => __('client phone'),
            'milestones.*.name' => __('milestone name'),
            'milestones.*.amount' => __('milestone amount'),
            'milestones.*.due_date' => __('due date'),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['milestones.required' => __('Add at least one milestone.')];
    }

    /** @return array<string, mixed> */
    public function jobData(int $clientId): array
    {
        return [
            'client_id' => $clientId,
            'title' => $this->string('title')->toString(),
            'site_address' => $this->string('site_address')->toString(),
            'quote_cents' => Money::toCents($this->string('quote')->toString()),
            'start_date' => $this->input('start_date') ?: null,
            'target_date' => $this->input('target_date') ?: null,
        ];
    }

    /** @return list<array{name: string, amount_cents: int, due_date: string|null}> */
    public function milestoneRows(): array
    {
        /** @var array<int, array{name: string, amount: string, due_date?: string|null}> $rows */
        $rows = $this->validated('milestones');

        return array_values(array_map(fn (array $row) => [
            'name' => $row['name'],
            'amount_cents' => Money::toCents($row['amount']),
            'due_date' => ($row['due_date'] ?? null) ?: null,
        ], $rows));
    }
}
