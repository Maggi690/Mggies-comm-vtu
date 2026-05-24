<?php
namespace App\Http\Requests\Auth;
use Illuminate\Foundation\Http\FormRequest;
class SetPinRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array {
        return ['pin' => 'required|digits:4', 'pin_confirmation' => 'required|digits:4'];
    }
}
