<?php

namespace Zarcony\Transactions\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreditRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'reciever_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|between:1,200'
        ];
    }
}
