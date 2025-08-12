<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Model para Work Orders (Ordens de Serviço/Agendamentos)
 * 
 * @property int $work_order_id
 * @property string $work_order_number
 * @property int $contractor_id
 * @property int $customer_id
 * @property int $vehicle_id
 * @property int $technician_id
 * @property int $service_provider_id
 * @property int $service_type_id
 * @property string $address
 * @property string $street_number
 * @property string $complement
 * @property string $district
 * @property int $city_id
 * @property string $postal_code
 * @property Carbon $scheduled_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property string $status
 * @property int $priority
 * @property string|null $cancellation_reason
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by_user_id
 * @property string|null $failed_visit_reason
 * @property Carbon|null $failed_visit_at
 * @property bool $is_warranty
 * @property string|null $warranty_reference
 * @property string|null $observations
 * @property string|null $internal_notes
 * @property float|null $estimated_cost
 * @property float|null $actual_cost
 * @property Carbon $created_at
 * @property int $created_by_user_id
 * @property Carbon $updated_at
 * @property int $updated_by_user_id
 */
class WorkOrder extends Model
{
    /**
     * A tabela associada ao modelo.
     *
     * @var string
     */
    protected $table = 'erp.work_orders';

    /**
     * A chave primária da tabela.
     *
     * @var string
     */
    protected $primaryKey = 'work_order_id';

    /**
     * Indica se o modelo deve ser timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * O nome da coluna "created at".
     *
     * @var string
     */
    const CREATED_AT = 'created_at';

    /**
     * O nome da coluna "updated at".
     *
     * @var string
     */
    const UPDATED_AT = 'updated_at';

    /**
     * Os atributos que são mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'contractor_id',
        'customer_id',
        'vehicle_id',
        'technician_id',
        'service_provider_id',
        'service_type_id',
        'address',
        'street_number',
        'complement',
        'district',
        'city_id',
        'postal_code',
        'scheduled_at',
        'started_at',
        'completed_at',
        'status',
        'priority',
        'cancellation_reason',
        'cancelled_at',
        'cancelled_by_user_id',
        'failed_visit_reason',
        'failed_visit_at',
        'is_warranty',
        'warranty_reference',
        'observations',
        'internal_notes',
        'estimated_cost',
        'actual_cost',
        'created_by_user_id',
        'updated_by_user_id'
    ];

    /**
     * Os atributos que devem ser convertidos para tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'failed_visit_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'is_warranty' => 'boolean',
        'estimated_cost' => 'decimal:2',
        'actual_cost' => 'decimal:2',
        'priority' => 'integer'
    ];

    /**
     * Os valores padrão dos atributos do modelo.
     *
     * @var array
     */
    protected $attributes = [
        'status' => 'pending',
        'priority' => 3,
        'is_warranty' => false
    ];

    /**
     * Status disponíveis
     */
    const STATUS_PENDING = 'pending';
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_FAILED_VISIT = 'failed_visit';
    const STATUS_RESCHEDULED = 'rescheduled';

    /**
     * Prioridades
     */
    const PRIORITY_VERY_HIGH = 1;
    const PRIORITY_HIGH = 2;
    const PRIORITY_NORMAL = 3;
    const PRIORITY_LOW = 4;
    const PRIORITY_VERY_LOW = 5;

    /**
     * Boot do modelo - triggers automáticos
     */
    protected static function boot()
    {
        parent::boot();

        // Antes de criar
        static::creating(function ($workOrder) {
            // Se não tem created_at, define agora
            if (!$workOrder->created_at) {
                $workOrder->created_at = Carbon::now();
            }
            
            // Se não tem updated_at, define agora
            if (!$workOrder->updated_at) {
                $workOrder->updated_at = Carbon::now();
            }
        });

        // Antes de atualizar
        static::updating(function ($workOrder) {
            // Atualiza o updated_at
            $workOrder->updated_at = Carbon::now();
        });
    }

    // ================== RELACIONAMENTOS ==================

    /**
     * Relacionamento com o cliente
     */
    public function customer()
    {
        return $this->belongsTo(Entity::class, 'customer_id', 'entityid');
    }

    /**
     * Relacionamento com o veículo
     */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'vehicleid');
    }

    /**
     * Relacionamento com o técnico
     */
    public function technician()
    {
        return $this->belongsTo(Technician::class, 'technician_id', 'technicianid');
    }

    /**
     * Relacionamento com o prestador de serviço
     */
    public function serviceProvider()
    {
        return $this->belongsTo(Entity::class, 'service_provider_id', 'entityid');
    }

    /**
     * Relacionamento com o tipo de serviço
     */
    public function serviceType()
    {
        return $this->belongsTo(ServiceType::class, 'service_type_id', 'service_type_id');
    }

    /**
     * Relacionamento com a cidade
     */
    public function city()
    {
        return $this->belongsTo(City::class, 'city_id', 'cityid');
    }

    /**
     * Relacionamento com o contratante
     */
    public function contractor()
    {
        return $this->belongsTo(Entity::class, 'contractor_id', 'entityid');
    }

    /**
     * Relacionamento com o usuário que criou
     */
    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'userid');
    }

    /**
     * Relacionamento com o usuário que atualizou
     */
    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'updated_by_user_id', 'userid');
    }

    /**
     * Relacionamento com o usuário que cancelou
     */
    public function cancelledByUser()
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id', 'userid');
    }

    /**
     * Relacionamento com o histórico
     */
    public function history()
    {
        return $this->hasMany(WorkOrderHistory::class, 'work_order_id', 'work_order_id');
    }

    // ================== SCOPES ==================

    /**
     * Scope para buscar apenas pendentes
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope para buscar apenas agendados
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    /**
     * Scope para buscar apenas completos
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope para buscar por contratante
     */
    public function scopeByContractor($query, $contractorId)
    {
        return $query->where('contractor_id', $contractorId);
    }

    /**
     * Scope para buscar por período
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('scheduled_at', [$startDate, $endDate]);
    }

    /**
     * Scope para buscar por técnico
     */
    public function scopeByTechnician($query, $technicianId)
    {
        return $query->where('technician_id', $technicianId);
    }

    /**
     * Scope para buscar por cliente
     */
    public function scopeByCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope para buscar emergências
     */
    public function scopeEmergencies($query)
    {
        return $query->whereIn('priority', [self::PRIORITY_VERY_HIGH, self::PRIORITY_HIGH]);
    }

    // ================== MÉTODOS AUXILIARES ==================

    /**
     * Verifica se é uma emergência
     */
    public function isEmergency()
    {
        return in_array($this->priority, [self::PRIORITY_VERY_HIGH, self::PRIORITY_HIGH]);
    }

    /**
     * Verifica se está pendente
     */
    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Verifica se está completo
     */
    public function isCompleted()
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Verifica se está cancelado
     */
    public function isCancelled()
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Verifica se está em progresso
     */
    public function isInProgress()
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    /**
     * Marca como iniciado
     */
    public function markAsStarted($userId = null)
    {
        $this->status = self::STATUS_IN_PROGRESS;
        $this->started_at = Carbon::now();
        
        if ($userId) {
            $this->updated_by_user_id = $userId;
        }
        
        return $this->save();
    }

    /**
     * Marca como completo
     */
    public function markAsCompleted($userId = null)
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completed_at = Carbon::now();
        
        if ($userId) {
            $this->updated_by_user_id = $userId;
        }
        
        return $this->save();
    }

    /**
     * Cancela a ordem de serviço
     */
    public function cancel($reason, $userId)
    {
        $this->status = self::STATUS_CANCELLED;
        $this->cancellation_reason = $reason;
        $this->cancelled_at = Carbon::now();
        $this->cancelled_by_user_id = $userId;
        $this->updated_by_user_id = $userId;
        
        return $this->save();
    }

    /**
     * Marca como visita frustrada
     */
    public function markAsFailedVisit($reason, $userId)
    {
        $this->status = self::STATUS_FAILED_VISIT;
        $this->failed_visit_reason = $reason;
        $this->failed_visit_at = Carbon::now();
        $this->updated_by_user_id = $userId;
        
        return $this->save();
    }

    /**
     * Reagenda a ordem de serviço
     */
    public function reschedule($newDate, $userId)
    {
        $this->status = self::STATUS_RESCHEDULED;
        $this->scheduled_at = $newDate;
        $this->updated_by_user_id = $userId;
        
        return $this->save();
    }

    /**
     * Retorna o endereço completo formatado
     */
    public function getFullAddress()
    {
        $parts = [];
        
        if ($this->address) {
            $parts[] = $this->address;
        }
        
        if ($this->street_number) {
            $parts[] = $this->street_number;
        }
        
        if ($this->complement) {
            $parts[] = $this->complement;
        }
        
        if ($this->district) {
            $parts[] = $this->district;
        }
        
        if ($this->city) {
            $cityName = $this->city->name;
            if ($this->city->state) {
                $cityName .= '/' . $this->city->state;
            }
            $parts[] = $cityName;
        }
        
        if ($this->postal_code) {
            $parts[] = 'CEP: ' . $this->postal_code;
        }
        
        return implode(', ', $parts);
    }

    /**
     * Retorna a descrição do status
     */
    public function getStatusLabel()
    {
        $labels = [
            self::STATUS_PENDING => 'Pendente',
            self::STATUS_SCHEDULED => 'Agendado',
            self::STATUS_IN_PROGRESS => 'Em andamento',
            self::STATUS_COMPLETED => 'Concluído',
            self::STATUS_CANCELLED => 'Cancelado',
            self::STATUS_FAILED_VISIT => 'Visita frustrada',
            self::STATUS_RESCHEDULED => 'Reagendado'
        ];
        
        return $labels[$this->status] ?? $this->status;
    }

    /**
     * Retorna a cor do status para o calendário
     */
    public function getStatusColor()
    {
        $colors = [
            self::STATUS_PENDING => '#FBBF24',
            self::STATUS_SCHEDULED => '#3B82F6',
            self::STATUS_IN_PROGRESS => '#8B5CF6',
            self::STATUS_COMPLETED => '#10B981',
            self::STATUS_CANCELLED => '#EF4444',
            self::STATUS_FAILED_VISIT => '#F97316',
            self::STATUS_RESCHEDULED => '#06B6D4'
        ];
        
        return $colors[$this->status] ?? '#6B7280';
    }

    /**
     * Retorna o label da prioridade
     */
    public function getPriorityLabel()
    {
        $labels = [
            self::PRIORITY_VERY_HIGH => 'Muito Alta',
            self::PRIORITY_HIGH => 'Alta',
            self::PRIORITY_NORMAL => 'Normal',
            self::PRIORITY_LOW => 'Baixa',
            self::PRIORITY_VERY_LOW => 'Muito Baixa'
        ];
        
        return $labels[$this->priority] ?? 'Normal';
    }

    /**
     * Retorna a cor da prioridade
     */
    public function getPriorityColor()
    {
        $colors = [
            self::PRIORITY_VERY_HIGH => '#DC2626',
            self::PRIORITY_HIGH => '#F59E0B',
            self::PRIORITY_NORMAL => '#10B981',
            self::PRIORITY_LOW => '#3B82F6',
            self::PRIORITY_VERY_LOW => '#6B7280'
        ];
        
        return $colors[$this->priority] ?? '#6B7280';
    }
}