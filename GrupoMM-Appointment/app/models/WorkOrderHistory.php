<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Model para Histórico de Work Orders
 * 
 * @property int $history_id
 * @property int $work_order_id
 * @property string $field_name
 * @property string|null $old_value
 * @property string|null $new_value
 * @property string|null $change_reason
 * @property Carbon $changed_at
 * @property int $changed_by_user_id
 */
class WorkOrderHistory extends Model
{
    /**
     * A tabela associada ao modelo.
     *
     * @var string
     */
    protected $table = 'erp.work_orders_history';

    /**
     * A chave primária da tabela.
     *
     * @var string
     */
    protected $primaryKey = 'history_id';

    /**
     * Indica se o modelo deve ser timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Os atributos que são mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'work_order_id',
        'field_name',
        'old_value',
        'new_value',
        'change_reason',
        'changed_at',
        'changed_by_user_id'
    ];

    /**
     * Os atributos que devem ser convertidos para tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'changed_at' => 'datetime',
        'work_order_id' => 'integer',
        'changed_by_user_id' => 'integer'
    ];

    /**
     * Os valores padrão dos atributos do modelo.
     *
     * @var array
     */
    protected $attributes = [
        'changed_at' => null
    ];

    /**
     * Tipos de ação para log
     */
    const ACTION_CREATE = 'CREATE';
    const ACTION_UPDATE = 'UPDATE';
    const ACTION_DELETE = 'DELETE';
    const ACTION_STATUS_CHANGE = 'STATUS_CHANGE';
    const ACTION_RESCHEDULE = 'RESCHEDULE';
    const ACTION_CANCEL = 'CANCEL';
    const ACTION_START = 'START';
    const ACTION_COMPLETE = 'COMPLETE';
    const ACTION_FAILED_VISIT = 'FAILED_VISIT';

    /**
     * Campos que devem ser traduzidos para exibição
     */
    protected static $fieldLabels = [
        'status' => 'Status',
        'scheduled_at' => 'Data/Hora Agendada',
        'technician_id' => 'Técnico',
        'service_type_id' => 'Tipo de Serviço',
        'priority' => 'Prioridade',
        'address' => 'Endereço',
        'street_number' => 'Número',
        'complement' => 'Complemento',
        'district' => 'Bairro',
        'city_id' => 'Cidade',
        'postal_code' => 'CEP',
        'observations' => 'Observações',
        'internal_notes' => 'Notas Internas',
        'estimated_cost' => 'Custo Estimado',
        'actual_cost' => 'Custo Real',
        'cancellation_reason' => 'Motivo do Cancelamento',
        'failed_visit_reason' => 'Motivo da Visita Frustrada',
        'is_warranty' => 'Garantia',
        'warranty_reference' => 'Referência da Garantia'
    ];

    /**
     * Boot do modelo
     */
    protected static function boot()
    {
        parent::boot();

        // Antes de criar
        static::creating(function ($history) {
            if (!$history->changed_at) {
                $history->changed_at = Carbon::now();
            }
        });
    }

    // ================== RELACIONAMENTOS ==================

    /**
     * Relacionamento com a work order
     */
    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id', 'work_order_id');
    }

    /**
     * Relacionamento com o usuário que fez a alteração
     */
    public function changedByUser()
    {
        return $this->belongsTo(User::class, 'changed_by_user_id', 'userid');
    }

    // ================== SCOPES ==================

    /**
     * Scope para buscar por work order
     */
    public function scopeForWorkOrder($query, $workOrderId)
    {
        return $query->where('work_order_id', $workOrderId);
    }

    /**
     * Scope para buscar por usuário
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('changed_by_user_id', $userId);
    }

    /**
     * Scope para buscar por campo
     */
    public function scopeForField($query, $fieldName)
    {
        return $query->where('field_name', $fieldName);
    }

    /**
     * Scope para buscar por período
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('changed_at', [$startDate, $endDate]);
    }

    /**
     * Scope para ordenar por data
     */
    public function scopeLatestFirst($query)
    {
        return $query->orderBy('changed_at', 'desc');
    }

    /**
     * Scope para ordenar cronologicamente
     */
    public function scopeOldestFirst($query)
    {
        return $query->orderBy('changed_at', 'asc');
    }

    // ================== MÉTODOS AUXILIARES ==================

    /**
     * Retorna o label do campo alterado
     */
    public function getFieldLabel()
    {
        return self::$fieldLabels[$this->field_name] ?? ucfirst(str_replace('_', ' ', $this->field_name));
    }

    /**
     * Formata o valor para exibição
     */
    public function formatValue($value, $isOld = false)
    {
        if ($value === null || $value === '') {
            return '(vazio)';
        }

        // Formatação especial por tipo de campo
        switch ($this->field_name) {
            case 'status':
                return $this->formatStatus($value);
                
            case 'priority':
                return $this->formatPriority($value);
                
            case 'technician_id':
                return $this->formatTechnician($value);
                
            case 'service_type_id':
                return $this->formatServiceType($value);
                
            case 'city_id':
                return $this->formatCity($value);
                
            case 'scheduled_at':
            case 'started_at':
            case 'completed_at':
            case 'cancelled_at':
            case 'failed_visit_at':
                return $this->formatDateTime($value);
                
            case 'estimated_cost':
            case 'actual_cost':
                return $this->formatCurrency($value);
                
            case 'is_warranty':
                return $value ? 'Sim' : 'Não';
                
            default:
                return $value;
        }
    }

    /**
     * Formata o valor antigo
     */
    public function getFormattedOldValue()
    {
        return $this->formatValue($this->old_value, true);
    }

    /**
     * Formata o valor novo
     */
    public function getFormattedNewValue()
    {
        return $this->formatValue($this->new_value, false);
    }

    /**
     * Formata status
     */
    protected function formatStatus($value)
    {
        $statuses = [
            'pending' => 'Pendente',
            'scheduled' => 'Agendado',
            'in_progress' => 'Em andamento',
            'completed' => 'Concluído',
            'cancelled' => 'Cancelado',
            'failed_visit' => 'Visita frustrada',
            'rescheduled' => 'Reagendado'
        ];
        
        return $statuses[$value] ?? $value;
    }

    /**
     * Formata prioridade
     */
    protected function formatPriority($value)
    {
        $priorities = [
            '1' => 'Muito Alta',
            '2' => 'Alta',
            '3' => 'Normal',
            '4' => 'Baixa',
            '5' => 'Muito Baixa'
        ];
        
        return $priorities[$value] ?? $value;
    }

    /**
     * Formata técnico
     */
    protected function formatTechnician($value)
    {
        $technician = Technician::find($value);
        return $technician ? $technician->name : "Técnico #{$value}";
    }

    /**
     * Formata tipo de serviço
     */
    protected function formatServiceType($value)
    {
        $serviceType = ServiceType::find($value);
        return $serviceType ? $serviceType->getLabel() : "Serviço #{$value}";
    }

    /**
     * Formata cidade
     */
    protected function formatCity($value)
    {
        $city = City::find($value);
        return $city ? "{$city->name}/{$city->state}" : "Cidade #{$value}";
    }

    /**
     * Formata data/hora
     */
    protected function formatDateTime($value)
    {
        try {
            $date = Carbon::parse($value);
            return $date->format('d/m/Y H:i');
        } catch (\Exception $e) {
            return $value;
        }
    }

    /**
     * Formata valor monetário
     */
    protected function formatCurrency($value)
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }

    /**
     * Gera descrição da mudança
     */
    public function getChangeDescription()
    {
        $user = $this->changedByUser;
        $userName = $user ? $user->name : 'Sistema';
        
        $description = "{$userName} alterou {$this->getFieldLabel()}";
        
        if ($this->old_value !== null && $this->new_value !== null) {
            $description .= " de '{$this->getFormattedOldValue()}' para '{$this->getFormattedNewValue()}'";
        } elseif ($this->new_value !== null) {
            $description .= " para '{$this->getFormattedNewValue()}'";
        } elseif ($this->old_value !== null) {
            $description .= " (removido valor '{$this->getFormattedOldValue()}')";
        }
        
        if ($this->change_reason) {
            $description .= " - Motivo: {$this->change_reason}";
        }
        
        return $description;
    }

    /**
     * Retorna ícone baseado no tipo de alteração
     */
    public function getIcon()
    {
        $icons = [
            'status' => 'exchange',
            'scheduled_at' => 'calendar',
            'technician_id' => 'user',
            'service_type_id' => 'wrench',
            'priority' => 'flag',
            'cancellation_reason' => 'times circle',
            'failed_visit_reason' => 'exclamation triangle',
            'started_at' => 'play circle',
            'completed_at' => 'check circle',
            'address' => 'map marker',
            'observations' => 'sticky note',
            'internal_notes' => 'lock',
            'estimated_cost' => 'dollar',
            'actual_cost' => 'dollar'
        ];
        
        return $icons[$this->field_name] ?? 'info circle';
    }

    /**
     * Retorna cor baseada no tipo de alteração
     */
    public function getColor()
    {
        // Status changes
        if ($this->field_name === 'status') {
            switch ($this->new_value) {
                case 'completed':
                    return 'green';
                case 'cancelled':
                case 'failed_visit':
                    return 'red';
                case 'in_progress':
                    return 'blue';
                case 'rescheduled':
                    return 'orange';
                default:
                    return 'grey';
            }
        }
        
        // Priority changes
        if ($this->field_name === 'priority') {
            $priority = intval($this->new_value);
            if ($priority <= 2) return 'red';
            if ($priority == 3) return 'yellow';
            return 'green';
        }
        
        // Other fields
        $fieldColors = [
            'cancellation_reason' => 'red',
            'failed_visit_reason' => 'orange',
            'started_at' => 'blue',
            'completed_at' => 'green',
            'scheduled_at' => 'teal'
        ];
        
        return $fieldColors[$this->field_name] ?? 'grey';
    }

    // ================== MÉTODOS ESTÁTICOS ==================

    /**
     * Registra uma alteração
     */
    public static function logChange($workOrderId, $fieldName, $oldValue, $newValue, $userId, $reason = null)
    {
        return self::create([
            'work_order_id' => $workOrderId,
            'field_name' => $fieldName,
            'old_value' => is_array($oldValue) || is_object($oldValue) ? json_encode($oldValue) : $oldValue,
            'new_value' => is_array($newValue) || is_object($newValue) ? json_encode($newValue) : $newValue,
            'change_reason' => $reason,
            'changed_by_user_id' => $userId,
            'changed_at' => Carbon::now()
        ]);
    }

    /**
     * Registra múltiplas alterações
     */
    public static function logMultipleChanges($workOrderId, array $changes, $userId, $reason = null)
    {
        $logs = [];
        
        foreach ($changes as $fieldName => $values) {
            $logs[] = [
                'work_order_id' => $workOrderId,
                'field_name' => $fieldName,
                'old_value' => isset($values['old']) ? $values['old'] : null,
                'new_value' => isset($values['new']) ? $values['new'] : null,
                'change_reason' => $reason,
                'changed_by_user_id' => $userId,
                'changed_at' => Carbon::now()
            ];
        }
        
        return self::insert($logs);
    }

    /**
     * Registra criação de work order
     */
    public static function logCreation($workOrderId, $userId, array $initialData = [])
    {
        return self::create([
            'work_order_id' => $workOrderId,
            'field_name' => 'CREATED',
            'old_value' => null,
            'new_value' => json_encode($initialData),
            'change_reason' => 'Ordem de serviço criada',
            'changed_by_user_id' => $userId,
            'changed_at' => Carbon::now()
        ]);
    }

    /**
     * Registra exclusão de work order
     */
    public static function logDeletion($workOrderId, $userId, $reason = null)
    {
        return self::create([
            'work_order_id' => $workOrderId,
            'field_name' => 'DELETED',
            'old_value' => null,
            'new_value' => null,
            'change_reason' => $reason ?? 'Ordem de serviço excluída',
            'changed_by_user_id' => $userId,
            'changed_at' => Carbon::now()
        ]);
    }

    /**
     * Retorna timeline de alterações formatada
     */
    public static function getTimeline($workOrderId)
    {
        return self::forWorkOrder($workOrderId)
            ->with('changedByUser')
            ->oldestFirst()
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->history_id,
                    'date' => $item->changed_at->format('d/m/Y H:i'),
                    'user' => $item->changedByUser ? $item->changedByUser->name : 'Sistema',
                    'description' => $item->getChangeDescription(),
                    'icon' => $item->getIcon(),
                    'color' => $item->getColor(),
                    'field' => $item->field_name,
                    'old_value' => $item->getFormattedOldValue(),
                    'new_value' => $item->getFormattedNewValue(),
                    'reason' => $item->change_reason
                ];
            });
    }
}