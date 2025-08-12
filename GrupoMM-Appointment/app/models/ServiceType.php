<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model para Tipos de Serviço
 * 
 * @property int $service_type_id
 * @property string $name
 * @property string|null $description
 * @property int|null $estimated_duration
 * @property bool $requires_warranty
 * @property bool $is_active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ServiceType extends Model
{
    /**
     * A tabela associada ao modelo.
     *
     * @var string
     */
    protected $table = 'erp.service_types';

    /**
     * A chave primária da tabela.
     *
     * @var string
     */
    protected $primaryKey = 'service_type_id';

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
        'name',
        'description',
        'estimated_duration',
        'requires_warranty',
        'is_active'
    ];

    /**
     * Os atributos que devem ser convertidos para tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'estimated_duration' => 'integer',
        'requires_warranty' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Os valores padrão dos atributos do modelo.
     *
     * @var array
     */
    protected $attributes = [
        'requires_warranty' => false,
        'is_active' => true
    ];

    /**
     * Tipos de serviço (constantes para referência)
     * Devem corresponder aos valores na tabela
     */
    const INSTALL_MAIN_TRACKER = 'Instalacao_Rastreador_Principal';
    const INSTALL_BACKUP_TRACKER = 'Instalacao_Rastreador_Contingencia';
    const MAINTENANCE_MAIN_TRACKER = 'Manutencao_Rastreador_Principal';
    const MAINTENANCE_BACKUP_TRACKER = 'Manutencao_Rastreador_Contingencia';
    const REMOVE_MAIN_TRACKER = 'Retirada_Rastreador_Principal';
    const REMOVE_BACKUP_TRACKER = 'Retirada_Rastreador_Contingencia';
    const INSTALL_VIDEOTELEMETRY = 'Instalacao_VideoTelemetria';
    const MAINTENANCE_VIDEOTELEMETRY = 'Manutencao_VideoTelemetria';
    const REMOVE_VIDEOTELEMETRY = 'Retirada_VideoTelemetria';
    const INSTALL_ACCESSORY = 'Instalacao_Acessorio';
    const TRANSFER_TRACKER = 'Transferencia_Rastreador';
    const EMERGENCY = 'Emergencia';
    const INSPECTION = 'Vistoria';

    /**
     * Mapeamento de nomes internos para labels amigáveis
     */
    protected static $serviceTypeLabels = [
        'Instalacao_Rastreador_Principal' => 'Instalação de Rastreador (Principal)',
        'Instalacao_Rastreador_Contingencia' => 'Instalação de Rastreador (Contingência)',
        'Manutencao_Rastreador_Principal' => 'Manutenção de Rastreador (Principal)',
        'Manutencao_Rastreador_Contingencia' => 'Manutenção de Rastreador (Contingência)',
        'Retirada_Rastreador_Principal' => 'Retirada de Rastreador (Principal)',
        'Retirada_Rastreador_Contingencia' => 'Retirada de Rastreador (Contingência)',
        'Instalacao_VideoTelemetria' => 'Instalação de VideoTelemetria',
        'Manutencao_VideoTelemetria' => 'Manutenção de VideoTelemetria',
        'Retirada_VideoTelemetria' => 'Retirada de VideoTelemetria',
        'Instalacao_Acessorio' => 'Instalação de Acessório',
        'Transferencia_Rastreador' => 'Transferência de Rastreador',
        'Emergencia' => 'Emergência',
        'Vistoria' => 'Vistoria'
    ];

    /**
     * Categorias dos serviços
     */
    protected static $serviceCategories = [
        'tracker' => [
            'Instalacao_Rastreador_Principal',
            'Instalacao_Rastreador_Contingencia',
            'Manutencao_Rastreador_Principal',
            'Manutencao_Rastreador_Contingencia',
            'Retirada_Rastreador_Principal',
            'Retirada_Rastreador_Contingencia',
            'Transferencia_Rastreador'
        ],
        'video' => [
            'Instalacao_VideoTelemetria',
            'Manutencao_VideoTelemetria',
            'Retirada_VideoTelemetria'
        ],
        'accessory' => [
            'Instalacao_Acessorio'
        ],
        'general' => [
            'Emergencia',
            'Vistoria'
        ]
    ];

    // ================== RELACIONAMENTOS ==================

    /**
     * Relacionamento com work orders
     */
    public function workOrders()
    {
        return $this->hasMany(WorkOrder::class, 'service_type_id', 'service_type_id');
    }

    // ================== SCOPES ==================

    /**
     * Scope para buscar apenas ativos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para buscar por categoria
     */
    public function scopeByCategory($query, $category)
    {
        if (isset(self::$serviceCategories[$category])) {
            return $query->whereIn('name', self::$serviceCategories[$category]);
        }
        return $query;
    }

    /**
     * Scope para buscar serviços que requerem garantia
     */
    public function scopeRequiresWarranty($query)
    {
        return $query->where('requires_warranty', true);
    }

    /**
     * Scope para buscar serviços de instalação
     */
    public function scopeInstallation($query)
    {
        return $query->where('name', 'like', 'Instalacao_%');
    }

    /**
     * Scope para buscar serviços de manutenção
     */
    public function scopeMaintenance($query)
    {
        return $query->where('name', 'like', 'Manutencao_%');
    }

    /**
     * Scope para buscar serviços de retirada
     */
    public function scopeRemoval($query)
    {
        return $query->where('name', 'like', 'Retirada_%');
    }

    // ================== MÉTODOS AUXILIARES ==================

    /**
     * Retorna o label amigável do tipo de serviço
     */
    public function getLabel()
    {
        return self::$serviceTypeLabels[$this->name] ?? $this->description ?? $this->name;
    }

    /**
     * Retorna a categoria do serviço
     */
    public function getCategory()
    {
        foreach (self::$serviceCategories as $category => $services) {
            if (in_array($this->name, $services)) {
                return $category;
            }
        }
        return 'general';
    }

    /**
     * Retorna o nome da categoria formatado
     */
    public function getCategoryLabel()
    {
        $labels = [
            'tracker' => 'Rastreador',
            'video' => 'VideoTelemetria',
            'accessory' => 'Acessórios',
            'general' => 'Serviços Gerais'
        ];
        
        $category = $this->getCategory();
        return $labels[$category] ?? 'Outros';
    }

    /**
     * Retorna o ícone da categoria
     */
    public function getCategoryIcon()
    {
        $icons = [
            'tracker' => '🛰️',
            'video' => '📹',
            'accessory' => '⚙️',
            'general' => '📋'
        ];
        
        $category = $this->getCategory();
        return $icons[$category] ?? '📌';
    }

    /**
     * Verifica se é um serviço de emergência
     */
    public function isEmergency()
    {
        return $this->name === self::EMERGENCY;
    }

    /**
     * Verifica se é um serviço de instalação
     */
    public function isInstallation()
    {
        return strpos($this->name, 'Instalacao_') === 0;
    }

    /**
     * Verifica se é um serviço de manutenção
     */
    public function isMaintenance()
    {
        return strpos($this->name, 'Manutencao_') === 0;
    }

    /**
     * Verifica se é um serviço de retirada
     */
    public function isRemoval()
    {
        return strpos($this->name, 'Retirada_') === 0;
    }

    /**
     * Retorna a duração estimada formatada
     */
    public function getFormattedDuration()
    {
        if (!$this->estimated_duration) {
            return 'Não especificado';
        }
        
        $hours = floor($this->estimated_duration / 60);
        $minutes = $this->estimated_duration % 60;
        
        if ($hours > 0 && $minutes > 0) {
            return "{$hours}h {$minutes}min";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$minutes}min";
        }
    }

    /**
     * Retorna todos os tipos de serviço como array para dropdowns
     */
    public static function getForDropdown($onlyActive = true)
    {
        $query = self::query();
        
        if ($onlyActive) {
            $query->active();
        }
        
        return $query->orderBy('name')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->service_type_id => $item->getLabel()];
            })
            ->toArray();
    }

    /**
     * Retorna tipos de serviço agrupados por categoria
     */
    public static function getGroupedForDropdown($onlyActive = true)
    {
        $query = self::query();
        
        if ($onlyActive) {
            $query->active();
        }
        
        $services = $query->orderBy('name')->get();
        $grouped = [];
        
        foreach ($services as $service) {
            $category = $service->getCategoryLabel();
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][$service->service_type_id] = $service->getLabel();
        }
        
        return $grouped;
    }

    /**
     * Busca o tipo de serviço pelo nome interno
     */
    public static function findByName($name)
    {
        return self::where('name', $name)->first();
    }

    /**
     * Cria ou atualiza um tipo de serviço
     */
    public static function createOrUpdateByName($name, $attributes = [])
    {
        $serviceType = self::firstOrNew(['name' => $name]);
        
        // Define descrição padrão se não fornecida
        if (!isset($attributes['description']) && isset(self::$serviceTypeLabels[$name])) {
            $attributes['description'] = self::$serviceTypeLabels[$name];
        }
        
        $serviceType->fill($attributes);
        $serviceType->save();
        
        return $serviceType;
    }

    /**
     * Retorna os serviços disponíveis baseado no estado do veículo
     */
    public static function getAvailableForVehicle($hasMainTracker, $hasBackupTracker, $hasVideoTelemetry)
    {
        $available = [];
        
        // Lógica para rastreador principal
        if (!$hasMainTracker) {
            $available[] = self::INSTALL_MAIN_TRACKER;
        } else {
            $available[] = self::MAINTENANCE_MAIN_TRACKER;
            $available[] = self::REMOVE_MAIN_TRACKER;
            $available[] = self::TRANSFER_TRACKER;
        }
        
        // Lógica para rastreador contingência
        if ($hasMainTracker && !$hasBackupTracker) {
            $available[] = self::INSTALL_BACKUP_TRACKER;
        } elseif ($hasBackupTracker) {
            $available[] = self::MAINTENANCE_BACKUP_TRACKER;
            $available[] = self::REMOVE_BACKUP_TRACKER;
        }
        
        // Lógica para videotelemetria
        if ($hasMainTracker) {
            if (!$hasVideoTelemetry) {
                $available[] = self::INSTALL_VIDEOTELEMETRY;
            } else {
                $available[] = self::MAINTENANCE_VIDEOTELEMETRY;
                $available[] = self::REMOVE_VIDEOTELEMETRY;
            }
        }
        
        // Acessórios (sempre disponível se tem rastreador)
        if ($hasMainTracker || $hasBackupTracker) {
            $available[] = self::INSTALL_ACCESSORY;
        }
        
        // Serviços gerais sempre disponíveis
        $available[] = self::EMERGENCY;
        $available[] = self::INSPECTION;
        
        return self::whereIn('name', $available)->active()->get();
    }
}