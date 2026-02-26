<?php

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

class EditDealForm extends Form
{
    #[Validate('required|min:2', as: 'título')]
    public string $title = '';

    #[Validate('required|numeric|min:0.01', as: 'valor')]
    public string $value = '';
}
