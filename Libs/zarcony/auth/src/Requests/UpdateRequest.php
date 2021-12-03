<?php

namespace SamirEltabal\AuthSystem\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
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
     * 
     */
    public function rules()
    {
        return [
            'form_type' => 'required|in:password,data',
            'password' => 'required_if:form_type,password',
            'new_password' => 'required_if:form_type,password|confirmed',
            'name' =>  'required_if:form_type,data|min:5|max:100',
            'phone' =>  'required_if:form_type,data|unique:users,phone'.$this->user()->id,
            'email' =>  'required_if:form_type,data|unique:users,email,'.$this->user()->id,
        ];
    }

    public function bodyParameters()
    {
        return [
            'email' => [
                'description' => 'new email of user or the current email without change.',
            ],
            'phone' => [
                'description' => 'new phone of user or the current phone without change.',
            ]
        ];
    }
}
