<?php

namespace App\Validators;

class AppointmentValidator
{
    public static function validate(array $data): array
    {
        $required = ['customer_id', 'vehicle_id', 'technician_id', 'service_type', 'address', 'scheduled_at'];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                return [
                    'success' => false,
                    'message' => "O campo '{$field}' é obrigatório."
                ];
            }
        }

        // Validação extra: formato de data/hora
        if (!self::isValidDatetime($data['scheduled_at'])) {
            return [
                'success' => false,
                'message' => "Data/hora inválida."
            ];
        }

        return ['success' => true];
    }

    protected static function isValidDatetime(string $datetime): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d H:i:s', $datetime);
        return $d && $d->format('Y-m-d H:i:s') === $datetime;
    }
}
