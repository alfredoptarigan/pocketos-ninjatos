<?php

namespace App\Http\Requests;

use App\Models\Item;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BuyItemRequest extends FormRequest
{
    /** Upper bound per purchase; the item's own stack limit is checked on purchase. */
    private const MAX_QUANTITY = 100;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->character()->exists() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'item_id' => ['required', 'integer', Rule::exists('items', 'id')->where('category', Item::CATEGORY_PHARMACY)],
            'quantity' => ['required', 'integer', 'between:1,'.self::MAX_QUANTITY],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'item_id' => 'item',
            'quantity' => 'quantity',
        ];
    }
}
