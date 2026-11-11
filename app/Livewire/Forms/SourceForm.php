<?php

namespace App\Livewire\Forms;

use App\Models\Source;
use App\Support\Link;
use Illuminate\Validation\Rule;
use Livewire\Form;

class SourceForm extends Form
{
    public ?string $url = null;

    /** Optional when there's a link: the source is then named after its site */
    public ?string $title = null;

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            // Only http(s), so a source link can never be a `javascript:` URL
            'url' => ['nullable', 'url:http,https', 'max:2048'],
            'title' => [Rule::requiredIf(fn () => blank($this->url)), 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'title.required' => __('Add a link or a title.'),
        ];
    }

    /**
     * The validated values, ready for create() or update(): an empty link as null, an empty title as the site.
     *
     * @return array{title: string, url: string|null}
     */
    public function data(): array
    {
        $validated = $this->validate();

        $url = filled($validated['url'] ?? null) ? (string) $validated['url'] : null;
        $title = trim((string) ($validated['title'] ?? ''));

        return [
            'title' => $title !== '' ? $title : Link::host((string) $url),
            'url' => $url,
        ];
    }

    public function fillFrom(Source $source): void
    {
        $this->fill($source->only(['title', 'url']));
    }
}
