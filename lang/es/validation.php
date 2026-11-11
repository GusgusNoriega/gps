<?php

return [
    'required' => 'El campo :attribute es obligatorio.',
    'required_without' => 'El campo :attribute es obligatorio cuando falta :values.',
    'string' => 'El campo :attribute debe ser texto.',
    'email' => 'Ingresa un correo electrónico válido.',
    'numeric' => 'El campo :attribute debe ser numérico.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'array' => 'El campo :attribute debe ser una lista.',
    'uuid' => 'El campo :attribute debe ser un UUID válido.',
    'date' => 'El campo :attribute debe ser una fecha válida.',
    'date_format' => 'El campo :attribute debe tener el formato :format.',
    'regex' => 'El formato de :attribute no es válido.',
    'after_or_equal' => 'El campo :attribute debe ser posterior o igual a :date.',
    'in' => 'El valor de :attribute no está permitido.',
    'exists' => 'El elemento seleccionado en :attribute no existe.',
    'unique' => 'El valor de :attribute ya está registrado.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'current_password' => 'La contraseña actual no es correcta.',
    'between' => [
        'numeric' => 'El campo :attribute debe estar entre :min y :max.',
    ],
    'min' => [
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
        'array' => 'El campo :attribute debe contener al menos :min elemento(s).',
        'numeric' => 'El campo :attribute debe ser al menos :min.',
    ],
    'max' => [
        'string' => 'El campo :attribute no puede superar :max caracteres.',
        'array' => 'El campo :attribute no puede contener más de :max elementos.',
        'numeric' => 'El campo :attribute no puede ser mayor que :max.',
    ],
    'gte' => ['numeric' => 'El campo :attribute debe ser mayor o igual a :value.'],
    'lt' => ['numeric' => 'El campo :attribute debe ser menor que :value.'],
    'password' => [
        'letters' => 'La contraseña debe contener al menos una letra.',
        'numbers' => 'La contraseña debe contener al menos un número.',
    ],
    'attributes' => [
        'name' => 'nombre', 'email' => 'correo electrónico', 'password' => 'contraseña',
        'current_password' => 'contraseña actual', 'from' => 'desde', 'to' => 'hasta',
        'device_id' => 'dispositivo', 'asset_id' => 'teléfono o vehículo',
        'moving_interval' => 'intervalo en movimiento', 'stationary_interval' => 'intervalo detenido',
        'stale_after' => 'umbral de ubicación antigua', 'enrollment_open' => 'nuevas vinculaciones',
    ],
];
