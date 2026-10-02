<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UseItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->character()->exists() ?? false;
    }

    /**
     * Only items in this player's bag that restore health or chakra can be used.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $characterId = $this->user()->character->id;

        return [
            'item_id' => [
                'required',
                'integer',
                Rule::exists('inventory_items', 'item_id')->where('character_id', $characterId),
                Rule::exists('items', 'id')->where(
                    fn ($query) => $query->where('restore_hp', '>', 0)->orWhere('restore_chakra', '>', 0),
                ),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'item_id.exists' => 'That item is not in your bag or cannot be used here.',
        ];
    }
}
