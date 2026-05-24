<?php
namespace App\Http\Requests\Auth;
use Illuminate\Foundation\Http\FormRequest;
class ResetPinRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array {
        return ['current_password' => 'required|string', 'new_pin' => 'required|digits:4', 'new_pin_confirmation' => 'required|digits:4'];
    }
}
