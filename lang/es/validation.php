<?php

return [
    'required' => 'El campo :attribute es obligatorio.',
    'email' => 'El campo :attribute debe ser un correo válido.',
    'unique' => 'El valor de :attribute ya está registrado.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'min' => ['string' => 'El campo :attribute debe tener al menos :min caracteres.', 'numeric' => 'El campo :attribute debe ser al menos :min.', 'array' => 'Agrega al menos :min elemento.'],
    'max' => ['string' => 'El campo :attribute no debe superar :max caracteres.', 'numeric' => 'El campo :attribute no debe superar :max.', 'array' => 'No se permiten más de :max elementos.'],
    'integer' => 'El campo :attribute debe ser un número entero.',
    'regex' => 'El campo :attribute tiene un formato inválido.',
    'exists' => 'El valor seleccionado para :attribute no está disponible.',
    'digits' => 'El campo :attribute debe tener :digits dígitos.',
    'date' => 'El campo :attribute debe ser una fecha válida.',
    'date_format' => 'El campo :attribute debe usar el formato :format.',
    'after_or_equal' => 'El campo :attribute debe ser igual o posterior a :date.',
    'in' => 'El valor de :attribute no es válido.',
    'uuid' => 'La operación no tiene un identificador válido. Recarga el formulario.',
    'attributes' => ['name' => 'nombre', 'business_name' => 'nombre del negocio', 'owner_name' => 'propietario', 'document_number' => 'DNI del propietario', 'code' => 'código de tienda', 'identity' => 'usuario o correo', 'password' => 'contraseña', 'email' => 'correo electrónico', 'username' => 'usuario', 'price' => 'precio', 'cost' => 'costo', 'quantity' => 'cantidad', 'reason' => 'motivo', 'subscription_until' => 'vencimiento', 'plan' => 'plan', 'received' => 'efectivo recibido', 'discount' => 'descuento', 'items' => 'productos', 'phone' => 'teléfono', 'address' => 'dirección', 'document' => 'documento'],
];
