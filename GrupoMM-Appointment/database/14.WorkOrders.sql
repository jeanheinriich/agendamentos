-- 1. ENUM para status da ordem de serviço
CREATE TYPE erp.work_order_status AS ENUM (
    'pending',           -- Pendente
    'scheduled',         -- Agendado
    'in_progress',       -- Em andamento
    'completed',         -- Concluído
    'cancelled',         -- Cancelado
    'failed_visit',      -- Visita frustrada
    'rescheduled'        -- Reagendado
);

-- 2. Tabela de tipos de serviço (normalização)
CREATE TABLE IF NOT EXISTS erp.service_types (
    service_type_id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    estimated_duration INTEGER, -- em minutos
    requires_warranty BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 3. Tabela principal: WORK ORDERS (com particionamento) - CORRIGIDA
CREATE TABLE IF NOT EXISTS erp.work_orders (
    work_order_id SERIAL,
    work_order_number VARCHAR(20) NOT NULL, -- Número da OS (ex: WO-2025-000001)
    contractor_id INTEGER NOT NULL,
    customer_id INTEGER NOT NULL,
    vehicle_id INTEGER NOT NULL,
    technician_id INTEGER NOT NULL,
    service_provider_id INTEGER NOT NULL,
    service_type_id INTEGER NOT NULL,
    
    -- Endereço estruturado
    address VARCHAR(200) NOT NULL,
    street_number VARCHAR(20),
    complement VARCHAR(50),
    district VARCHAR(100),
    city_id INTEGER NOT NULL,
    postal_code CHAR(9) NOT NULL,
    
    -- Datas e horários
    scheduled_at TIMESTAMP WITHOUT TIME ZONE NOT NULL,
    started_at TIMESTAMP WITHOUT TIME ZONE,
    completed_at TIMESTAMP WITHOUT TIME ZONE,
    
    -- Status e controle
    status erp.work_order_status DEFAULT 'pending',
    priority INTEGER DEFAULT 3 CHECK (priority BETWEEN 1 AND 5), -- 1=Alta, 5=Baixa
    
    -- Cancelamento
    cancellation_reason TEXT, -- Campo livre para motivo do cancelamento
    cancelled_at TIMESTAMP WITHOUT TIME ZONE,
    cancelled_by_user_id INTEGER,
    
    -- Visita frustrada
    failed_visit_reason TEXT,
    failed_visit_at TIMESTAMP WITHOUT TIME ZONE,
    
    -- Garantia e observações
    is_warranty BOOLEAN DEFAULT FALSE,
    warranty_reference VARCHAR(50), -- Referência da OS original
    observations TEXT,
    internal_notes TEXT, -- Notas internas (não visíveis ao cliente)
    
    -- Valores (se aplicável)
    estimated_cost DECIMAL(10,2),
    actual_cost DECIMAL(10,2),
    
    -- Auditoria
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INTEGER NOT NULL,
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_by_user_id INTEGER NOT NULL,
    
    -- Constraints
    CONSTRAINT work_orders_pkey PRIMARY KEY (work_order_id, created_at),
    
    -- ✅ UNIQUE constraint incluindo a coluna de particionamento
    CONSTRAINT work_orders_number_unique UNIQUE (work_order_number, created_at),
    
    -- Foreign Keys
    CONSTRAINT fk_work_order_contractor FOREIGN KEY (contractor_id)
        REFERENCES erp.entities (entityid) MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE RESTRICT,
    CONSTRAINT fk_work_order_customer FOREIGN KEY (customer_id)
        REFERENCES erp.entities (entityid) MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE RESTRICT,
    CONSTRAINT fk_work_order_vehicle FOREIGN KEY (vehicle_id)
        REFERENCES erp.vehicles (vehicleid) MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE RESTRICT,
    CONSTRAINT fk_work_order_technician FOREIGN KEY (technician_id)
        REFERENCES erp.technicians (technicianid) MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE RESTRICT,
    CONSTRAINT fk_work_order_service_provider FOREIGN KEY (service_provider_id)
        REFERENCES erp.entities (entityid) MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE RESTRICT,
    CONSTRAINT fk_work_order_service_type FOREIGN KEY (service_type_id)
        REFERENCES erp.service_types (service_type_id) MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE RESTRICT,
    CONSTRAINT fk_work_order_city FOREIGN KEY (city_id)
        REFERENCES erp.cities (cityid) MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE RESTRICT,
    CONSTRAINT fk_work_order_created_user FOREIGN KEY (created_by_user_id)
        REFERENCES erp.users (userid) MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE RESTRICT,
    CONSTRAINT fk_work_order_updated_user FOREIGN KEY (updated_by_user_id)
        REFERENCES erp.users (userid) MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE RESTRICT,
    CONSTRAINT fk_work_order_cancelled_user FOREIGN KEY (cancelled_by_user_id)
        REFERENCES erp.users (userid) MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE RESTRICT,
        
    -- Business Rules
    CONSTRAINT chk_scheduled_before_started CHECK (scheduled_at <= started_at OR started_at IS NULL),
    CONSTRAINT chk_started_before_completed CHECK (started_at <= completed_at OR completed_at IS NULL),
    CONSTRAINT chk_cancellation_fields CHECK (
        (status = 'cancelled' AND cancellation_reason IS NOT NULL AND cancelled_at IS NOT NULL) OR
        (status != 'cancelled' AND cancellation_reason IS NULL AND cancelled_at IS NULL)
    ),
    CONSTRAINT chk_failed_visit_fields CHECK (
        (status = 'failed_visit' AND failed_visit_reason IS NOT NULL AND failed_visit_at IS NOT NULL) OR
        (status != 'failed_visit' AND failed_visit_reason IS NULL AND failed_visit_at IS NULL)
    ),
    CONSTRAINT chk_warranty_reference CHECK (
        (is_warranty = TRUE AND warranty_reference IS NOT NULL) OR
        (is_warranty = FALSE)
    )
) PARTITION BY RANGE (created_at);

-- 4. Tabela de histórico/log de alterações
CREATE TABLE IF NOT EXISTS erp.work_orders_history (
    history_id SERIAL PRIMARY KEY,
    work_order_id INTEGER NOT NULL,
    field_name VARCHAR(50) NOT NULL,
    old_value TEXT,
    new_value TEXT,
    change_reason VARCHAR(200),
    changed_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    changed_by_user_id INTEGER NOT NULL,
    
    CONSTRAINT fk_work_order_history_user FOREIGN KEY (changed_by_user_id)
        REFERENCES erp.users (userid) MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE RESTRICT
);

-- 5. Função para criar partições automáticas
CREATE OR REPLACE FUNCTION erp.work_orders_partition_trigger()
RETURNS TRIGGER AS
$BODY$
DECLARE
    year_of_date CHAR(4);
    month_of_date CHAR(2);
    start_of_month DATE;
    end_of_month DATE;
    partition_name VARCHAR;
    table_exists BOOLEAN;
BEGIN
    IF (TG_OP = 'INSERT') THEN
        -- Extrai ano e mês da data de criação
        year_of_date := EXTRACT(YEAR FROM NEW.created_at);
        month_of_date := LPAD(EXTRACT(MONTH FROM NEW.created_at)::VARCHAR, 2, '0');
        partition_name := 'work_orders_' || year_of_date || month_of_date;
        start_of_month := DATE_TRUNC('MONTH', NEW.created_at);
        end_of_month := (DATE_TRUNC('MONTH', NEW.created_at) + INTERVAL '1 MONTH - 1 day')::DATE;
        
        -- Verifica se a partição existe
        SELECT EXISTS(
            SELECT 1 FROM pg_catalog.pg_class c
            JOIN pg_catalog.pg_namespace n ON c.relnamespace = n.oid
            WHERE c.relname = partition_name AND n.nspname = 'erp'
        ) INTO table_exists;
        
        IF NOT table_exists THEN
            RAISE NOTICE 'Criando partição %/% para work_orders', month_of_date, year_of_date;
            
            -- Cria a partição
            EXECUTE FORMAT('CREATE TABLE erp.%I PARTITION OF erp.work_orders 
                          FOR VALUES FROM (%L) TO (%L)',
                          partition_name, start_of_month, end_of_month + 1);
            
            -- Cria índices na partição
            EXECUTE FORMAT('CREATE INDEX %I_created_at_idx ON erp.%I (created_at)', 
                          partition_name, partition_name);
            EXECUTE FORMAT('CREATE INDEX %I_customer_idx ON erp.%I (customer_id, created_at)', 
                          partition_name, partition_name);
            EXECUTE FORMAT('CREATE INDEX %I_technician_idx ON erp.%I (technician_id, scheduled_at)', 
                          partition_name, partition_name);
            EXECUTE FORMAT('CREATE INDEX %I_status_idx ON erp.%I (status, created_at)', 
                          partition_name, partition_name);
            EXECUTE FORMAT('CREATE INDEX %I_vehicle_idx ON erp.%I (vehicle_id, created_at)', 
                          partition_name, partition_name);
            EXECUTE FORMAT('CREATE INDEX %I_number_idx ON erp.%I (work_order_number)', 
                          partition_name, partition_name);
        END IF;
    END IF;
    
    RETURN NEW;
END;
$BODY$ LANGUAGE plpgsql;

-- 6. Trigger para particionamento automático
CREATE OR REPLACE TRIGGER work_orders_partition_trigger
    BEFORE INSERT ON erp.work_orders
    FOR EACH ROW
    EXECUTE FUNCTION erp.work_orders_partition_trigger();

-- 7. Função para gerar número sequencial da OS - CORRIGIDA
CREATE OR REPLACE FUNCTION erp.generate_work_order_number()
RETURNS TRIGGER AS
$BODY$
DECLARE
    current_year INTEGER;
    sequence_number INTEGER;
    new_number VARCHAR(20);
    number_exists BOOLEAN;
BEGIN
    current_year := EXTRACT(YEAR FROM NEW.created_at);
    
    -- Loop para garantir número único (caso de concorrência)
    LOOP
        -- Busca o próximo número sequencial para o ano
        SELECT COALESCE(MAX(
            CAST(SUBSTRING(work_order_number FROM '\d{4}-(\d{6})') AS INTEGER)
        ), 0) + 1
        INTO sequence_number
        FROM erp.work_orders
        WHERE work_order_number LIKE 'WO-' || current_year || '-%';
        
        -- Gera o número da OS no formato: WO-2025-000001
        new_number := 'WO-' || current_year || '-' || LPAD(sequence_number::TEXT, 6, '0');
        
        -- Verifica se o número já existe
        SELECT EXISTS(
            SELECT 1 FROM erp.work_orders WHERE work_order_number = new_number
        ) INTO number_exists;
        
        -- Se não existe, usa este número
        IF NOT number_exists THEN
            NEW.work_order_number := new_number;
            EXIT;
        END IF;
    END LOOP;
    
    RETURN NEW;
END;
$BODY$ LANGUAGE plpgsql;

-- 8. Trigger para gerar número da OS automaticamente
CREATE OR REPLACE TRIGGER work_order_number_trigger
    BEFORE INSERT ON erp.work_orders
    FOR EACH ROW
    EXECUTE FUNCTION erp.generate_work_order_number();

-- 9. Função para garantir unicidade do work_order_number
CREATE OR REPLACE FUNCTION erp.check_work_order_number_unique()
RETURNS TRIGGER AS
$BODY$
DECLARE
    count_existing INTEGER;
BEGIN
    -- Verifica se já existe outro registro com o mesmo work_order_number
    SELECT COUNT(*)
    INTO count_existing
    FROM erp.work_orders
    WHERE work_order_number = NEW.work_order_number
      AND (work_order_id != NEW.work_order_id OR NEW.work_order_id IS NULL);
    
    IF count_existing > 0 THEN
        RAISE EXCEPTION 'work_order_number % já existe', NEW.work_order_number
            USING ERRCODE = 'unique_violation';
    END IF;
    
    RETURN NEW;
END;
$BODY$ LANGUAGE plpgsql;

-- 10. Trigger para garantir unicidade do work_order_number
CREATE OR REPLACE TRIGGER work_order_number_unique_trigger
    BEFORE INSERT OR UPDATE ON erp.work_orders
    FOR EACH ROW
    EXECUTE FUNCTION erp.check_work_order_number_unique();

-- 11. Função para log de alterações
CREATE OR REPLACE FUNCTION erp.work_orders_audit_trigger()
RETURNS TRIGGER AS
$BODY$
DECLARE
    old_values JSONB;
    new_values JSONB;
    field_name TEXT;
    old_value TEXT;
    new_value TEXT;
BEGIN
    IF TG_OP = 'UPDATE' THEN
        old_values := to_jsonb(OLD);
        new_values := to_jsonb(NEW);
        
        -- Compara cada campo e registra as alterações
        FOR field_name IN 
            SELECT key FROM jsonb_each_text(new_values)
            WHERE key NOT IN ('updated_at', 'updated_by_user_id') -- Exclui campos de auditoria
        LOOP
            old_value := old_values ->> field_name;
            new_value := new_values ->> field_name;
            
            IF old_value IS DISTINCT FROM new_value THEN
                INSERT INTO erp.work_orders_history (
                    work_order_id, field_name, old_value, new_value, changed_by_user_id
                ) VALUES (
                    NEW.work_order_id, field_name, old_value, new_value, NEW.updated_by_user_id
                );
            END IF;
        END LOOP;
    END IF;
    
    RETURN NEW;
END;
$BODY$ LANGUAGE plpgsql;

-- 12. Trigger para auditoria
CREATE OR REPLACE TRIGGER work_orders_audit_trigger
    AFTER UPDATE ON erp.work_orders
    FOR EACH ROW
    EXECUTE FUNCTION erp.work_orders_audit_trigger();

-- 13. Função para atualizar updated_at automaticamente
CREATE OR REPLACE FUNCTION erp.update_timestamp()
RETURNS TRIGGER AS
$BODY$
BEGIN
    NEW.updated_at := CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$BODY$ LANGUAGE plpgsql;

-- 14. Trigger para updated_at
CREATE OR REPLACE TRIGGER work_orders_updated_at_trigger
    BEFORE UPDATE ON erp.work_orders
    FOR EACH ROW
    EXECUTE FUNCTION erp.update_timestamp();

-- 15. Dados iniciais para service_types (serviços reais da empresa)
INSERT INTO erp.service_types (name, description, estimated_duration, requires_warranty) VALUES
('Instalacao_Rastreador_Principal', 'Instalação de Rastreador (Principal)', 90, FALSE),
('Instalacao_Rastreador_Contingencia', 'Instalação de Rastreador (Contingência)', 60, FALSE),
('Manutencao_Rastreador_Principal', 'Manutenção de Rastreador (Principal)', 75, TRUE),
('Manutencao_Rastreador_Contingencia', 'Manutenção de Rastreador (Contingência)', 60, TRUE),
('Retirada_Rastreador_Principal', 'Retirada de Rastreador (Principal)', 45, FALSE),
('Retirada_Rastreador_Contingencia', 'Retirada de Rastreador (Contingência)', 30, FALSE),
('Instalacao_VideoTelemetria', 'Instalação de VideoTelemetria', 120, FALSE),
('Manutencao_VideoTelemetria', 'Manutenção de VideoTelemetria', 90, TRUE),
('Retirada_VideoTelemetria', 'Retirada de VideoTelemetria', 60, FALSE),
('Instalacao_Acessorio', 'Instalação de Acessório', 45, FALSE),
('Transferencia_Rastreador', 'Transferência de Rastreador', 90, FALSE),
('Emergencia', 'Emergência', 60, FALSE),
('Vistoria', 'Vistoria', 30, FALSE)
ON CONFLICT DO NOTHING;

-- 16. Comentários nas tabelas
COMMENT ON TABLE erp.work_orders IS 'Tabela principal de ordens de serviço com particionamento mensal';
COMMENT ON TABLE erp.work_orders_history IS 'Histórico de alterações nas ordens de serviço';
COMMENT ON TABLE erp.service_types IS 'Tipos de serviço disponíveis';

-- 17. Permissões
ALTER TABLE erp.work_orders OWNER TO admin;
ALTER TABLE erp.work_orders_history OWNER TO admin;
ALTER TABLE erp.service_types OWNER TO admin;

-- 18. Criação da primeira partição (exemplo para janeiro 2025)
CREATE TABLE IF NOT EXISTS erp.work_orders_202501 PARTITION OF erp.work_orders
FOR VALUES FROM ('2025-01-01') TO ('2025-02-01');

-- Índices para a primeira partição
CREATE INDEX IF NOT EXISTS work_orders_202501_created_at_idx ON erp.work_orders_202501 (created_at);
CREATE INDEX IF NOT EXISTS work_orders_202501_customer_idx ON erp.work_orders_202501 (customer_id, created_at);
CREATE INDEX IF NOT EXISTS work_orders_202501_technician_idx ON erp.work_orders_202501 (technician_id, scheduled_at);
CREATE INDEX IF NOT EXISTS work_orders_202501_status_idx ON erp.work_orders_202501 (status, created_at);
CREATE INDEX IF NOT EXISTS work_orders_202501_vehicle_idx ON erp.work_orders_202501 (vehicle_id, created_at);
CREATE INDEX IF NOT EXISTS work_orders_202501_number_idx ON erp.work_orders_202501 (work_order_number);

-- 19. Criação de mais partições para alguns meses à frente
CREATE TABLE IF NOT EXISTS erp.work_orders_202502 PARTITION OF erp.work_orders
FOR VALUES FROM ('2025-02-01') TO ('2025-03-01');

CREATE TABLE IF NOT EXISTS erp.work_orders_202503 PARTITION OF erp.work_orders
FOR VALUES FROM ('2025-03-01') TO ('2025-04-01');

-- Índices para as partições adicionais
CREATE INDEX IF NOT EXISTS work_orders_202502_created_at_idx ON erp.work_orders_202502 (created_at);
CREATE INDEX IF NOT EXISTS work_orders_202502_number_idx ON erp.work_orders_202502 (work_order_number);
CREATE INDEX IF NOT EXISTS work_orders_202503_created_at_idx ON erp.work_orders_202503 (created_at);
CREATE INDEX IF NOT EXISTS work_orders_202503_number_idx ON erp.work_orders_202503 (work_order_number);

-- 20. View para facilitar consultas ignorando a restrição de unicidade
CREATE OR REPLACE VIEW erp.work_orders_view AS
SELECT DISTINCT ON (work_order_number)
    work_order_id,
    work_order_number,
    contractor_id,
    customer_id,
    vehicle_id,
    technician_id,
    service_provider_id,
    service_type_id,
    address,
    street_number,
    complement,
    district,
    city_id,
    postal_code,
    scheduled_at,
    started_at,
    completed_at,
    status,
    priority,
    cancellation_reason,
    cancelled_at,
    cancelled_by_user_id,
    failed_visit_reason,
    failed_visit_at,
    is_warranty,
    warranty_reference,
    observations,
    internal_notes,
    estimated_cost,
    actual_cost,
    created_at,
    created_by_user_id,
    updated_at,
    updated_by_user_id
FROM erp.work_orders
ORDER BY work_order_number, created_at DESC;

COMMENT ON VIEW erp.work_orders_view IS 'View que retorna apenas um registro por work_order_number (o mais recente)';

-- ---------------------------------------------------------------------
-- Dados de endereços
-- ---------------------------------------------------------------------
-- Cria stored procedures que recuperam as informações dos endereços
-- ---------------------------------------------------------------------
CREATE TYPE erp.addressData AS
(
  rowNumber     integer,      -- O número de ordem da linha
  main          boolean,      -- O endereço principal
  place         varchar(100), -- O local
  address       varchar(50),  -- O endereço
  streetNumber  varchar(10),  -- O número da casa
  complement    varchar(30),  -- O complemento do endereço
  district      varchar(50),  -- O bairro
  postalCode    char(9),      -- O CEP
  cityID        integer,      -- O ID da cidade
  cityName      varchar(50),  -- O nome da cidade
  state         char(2),      -- A UF
  phones        jsonb         -- Os telefones de contato
);

-- ---------------------------------------------------------------------
-- Endereços dos locais do veículo
-- ---------------------------------------------------------------------
-- Recupera os endereços disponíveis para o local onde um veículo pode
-- encontrar-se em função dos endereços disponíveis no cadastro
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.getVehicleAddresses(
  FcontractorID integer, FvehicleID integer)
RETURNS SETOF erp.addressData AS
$$
DECLARE
  addressData  erp.addressData%rowtype;
  row  record;
  rowNumber  integer;
  addCustomerAddress  boolean;
  addOwnerAddress  boolean;
BEGIN
  IF (FcontractorID IS NULL) THEN
    FcontractorID := 0;
  END IF;
  IF (FvehicleID IS NULL) THEN
    FvehicleID := 0;
  END IF;

  -- Monta a consulta
  FOR row IN
    SELECT vehicle.customerIsTheOwner,
           customer.name AS customerName,
           customer.entityTypeID,
           unity.address AS customerAddress,
           unity.streetNumber AS customerStreetNumber,
           unity.complement AS customerComplement,
           unity.district AS customerDistrict,
           unity.postalCode AS customerPostalCode,
           unity.cityID AS customerCityID,
           unity.genderID,
           customerCity.name AS customerCityName,
           customerCity.state AS customerState,
           (SELECT jsonb_agg(jsonb_build_object('phoneTypeID', phoneTypeID, 'phoneNumber', phoneNumber) ORDER BY phoneID)
              FROM erp.phones
             WHERE subsidiaryID = unity.subsidiaryID) AS customerPhones,
           vehicle.ownerName,
           vehicle.address AS ownerAddress,
           vehicle.streetNumber AS ownerStreetNumber,
           vehicle.complement AS ownerComplement,
           vehicle.district AS ownerDistrict,
           vehicle.postalCode AS ownerPostalCode,
           vehicle.cityID AS ownerCityID,
           ownerCity.name AS ownerCityName,
           ownerCity.state AS ownerState,
           CASE
             WHEN NOT vehicle.customerIsTheOwner THEN
               (SELECT jsonb_agg(jsonb_build_object('phoneTypeID', phoneTypeID, 'phoneNumber', phoneNumber) ORDER BY ownerPhoneID)
                  FROM erp.ownerPhones
                 WHERE vehicleID = vehicle.vehicleID)
             ELSE '[]'::jsonb
           END AS ownerPhones,
           vehicle.atSameCustomerAddress,
           vehicle.atSameOwnerAddress,
           vehicle.atAnotherAddress,
           vehicle.anotherName,
           vehicle.anotherAddress,
           vehicle.anotherStreetNumber,
           vehicle.anotherComplement,
           vehicle.anotherDistrict,
           vehicle.anotherPostalCode,
           vehicle.anotherCityID,
           anotherCity.name AS anotherCityName,
           anotherCity.state AS anotherState,
           CASE
             WHEN vehicle.atAnotherAddress THEN
               (SELECT jsonb_agg(jsonb_build_object('phoneTypeID', phoneTypeID, 'phoneNumber', phoneNumber) ORDER BY anotherPhoneID)
                  FROM erp.anotherPhones
                 WHERE vehicleID = vehicle.vehicleID)
             ELSE '[]'::jsonb
           END AS anotherPhones
      FROM erp.vehicles AS vehicle
     INNER JOIN erp.entities AS customer ON (vehicle.customerID = customer.entityID)
     INNER JOIN erp.subsidiaries AS unity ON (vehicle.subsidiaryID = unity.subsidiaryID)
     INNER JOIN erp.cities AS customerCity ON (unity.cityID = customerCity.cityID)
      LEFT JOIN erp.cities AS ownerCity ON (vehicle.cityID = ownerCity.cityID)
      LEFT JOIN erp.cities AS anotherCity ON (vehicle.anotherCityID = anotherCity.cityID)
     WHERE vehicle.vehicleID = FvehicleID
       AND vehicle.contractorID = FcontractorID
  LOOP
    addCustomerAddress := false;
    addOwnerAddress := false;
    CASE
      WHEN row.atAnotherAddress THEN
        RAISE NOTICE 'Veículo está em outro endereço, então retorna-o.';
        addressData.place        := LEFT('No endereço de ' || row.anotherName, 100);
        addressData.address      := row.anotherAddress;
        addressData.streetNumber := row.anotherStreetNumber;
        addressData.complement   := row.anotherComplement;
        addressData.district     := row.anotherDistrict;
        addressData.postalCode   := row.anotherPostalCode;
        addressData.cityID       := row.anotherCityID;
        addressData.cityName     := row.anotherCityName;
        addressData.state        := row.anotherState;
        addressData.phones       := row.anotherPhones;

        -- Sempre adiciona o endereço do cliente
        addCustomerAddress := true;
        -- Se o cliente não for o dono, adiciona também o endereço do
        -- proprietário do veículo
        IF (NOT row.customerIsTheOwner) THEN
          addOwnerAddress := true;
        END IF;
      WHEN row.atSameCustomerAddress THEN
        RAISE NOTICE 'Veículo está no cliente, então retorna-o.';
        -- Lida com as questões de gênero
        IF (row.entityTypeID = 2) THEN
          CASE (row.genderID) 
            WHEN 3 THEN
              -- Feminino
              addressData.place  := LEFT('Na cliente ' || row.customerName, 100);
            WHEN 2 THEN
              -- Masculino
              addressData.place  := LEFT('No cliente ' || row.customerName, 100);
            ELSE
              addressData.place  := LEFT('No(a) cliente ' || row.customerName, 100);
          END CASE;
        ELSE
          addressData.place      := LEFT('Na sede do cliente ' || row.customerName, 100);
        END IF;
        addressData.address      := row.customerAddress;
        addressData.streetNumber := row.customerStreetNumber;
        addressData.complement   := row.customerComplement;
        addressData.district     := row.customerDistrict;
        addressData.postalCode   := row.customerPostalCode;
        addressData.cityID       := row.customerCityID;
        addressData.cityName     := row.customerCityName;
        addressData.state        := row.customerState;
        addressData.phones       := row.customerPhones;

        -- Se o cliente não for o dono, adiciona também o endereço do
        -- proprietário do veículo
        IF (NOT row.customerIsTheOwner) THEN
          addOwnerAddress := true;
        END IF;
      WHEN row.atSameOwnerAddress THEN
        RAISE NOTICE 'Veículo está no proprietário, então retorna-o.';
        IF (row.customerIsTheOwner) THEN
          -- Lida com as questões de gênero
          IF (row.entityTypeID = 2) THEN
            CASE (row.genderID) 
              WHEN 3 THEN
                -- Feminino
                addressData.place  := LEFT('Na cliente ' || row.customerName, 100);
              WHEN 2 THEN
                -- Masculino
                addressData.place  := LEFT('No cliente ' || row.customerName, 100);
              ELSE
                addressData.place  := LEFT('No(a) cliente ' || row.customerName, 100);
            END CASE;
          ELSE
            addressData.place      := LEFT('Na sede do cliente ' || row.customerName, 100);
          END IF;
          addressData.address      := row.customerAddress;
          addressData.streetNumber := row.customerStreetNumber;
          addressData.complement   := row.customerComplement;
          addressData.district     := row.customerDistrict;
          addressData.postalCode   := row.customerPostalCode;
          addressData.cityID       := row.customerCityID;
          addressData.cityName     := row.customerCityName;
          addressData.state        := row.customerState;
          addressData.phones       := row.customerPhones;
        ELSE
          addressData.place        := LEFT('No endereço do(a) proprietário(a) ' || row.ownerName, 100);
          addressData.address      := row.ownerAddress;
          addressData.streetNumber := row.ownerStreetNumber;
          addressData.complement   := row.ownerComplement;
          addressData.district     := row.ownerDistrict;
          addressData.postalCode   := row.ownerPostalCode;
          addressData.cityID       := row.ownerCityID;
          addressData.cityName     := row.ownerCityName;
          addressData.state        := row.ownerState;
          addressData.phones       := row.ownerPhones;

          -- Adiciona também o endereço do cliente
          addCustomerAddress := true;
        END IF;
    END CASE;

    -- Retornamos inicialmente o endereço do local onde o veículo
    -- encontra-se
    rowNumber := 1;
    addressData.rowNumber := rowNumber;
    addressData.main      := true;

    RETURN NEXT addressData;

    IF (addOwnerAddress) THEN
      rowNumber := rowNumber + 1;
      addressData.rowNumber    := rowNumber;
      addressData.main         := false;
      addressData.place        := LEFT('No endereço do(a) proprietário(a) ' || row.ownerName, 100);
      addressData.address      := row.ownerAddress;
      addressData.streetNumber := row.ownerStreetNumber;
      addressData.complement   := row.ownerComplement;
      addressData.district     := row.ownerDistrict;
      addressData.postalCode   := row.ownerPostalCode;
      addressData.cityID       := row.ownerCityID;
      addressData.cityName     := row.ownerCityName;
      addressData.state        := row.ownerState;
      addressData.phones       := row.ownerPhones;

      RETURN NEXT addressData;
    END IF;

    IF (addCustomerAddress) THEN
      rowNumber := rowNumber + 1;
      addressData.rowNumber    := rowNumber;
      addressData.main         := false;
      -- Lida com as questões de gênero
      IF (row.entityTypeID = 2) THEN
        CASE (row.genderID) 
          WHEN 3 THEN
            -- Feminino
            addressData.place  := LEFT('Na cliente ' || row.customerName, 100);
          WHEN 2 THEN
            -- Masculino
            addressData.place  := LEFT('No cliente ' || row.customerName, 100);
          ELSE
            addressData.place  := LEFT('No(a) cliente ' || row.customerName, 100);
        END CASE;
      ELSE
        addressData.place      := LEFT('Na sede do cliente ' || row.customerName, 100);
      END IF;
      addressData.address      := row.customerAddress;
      addressData.streetNumber := row.customerStreetNumber;
      addressData.complement   := row.customerComplement;
      addressData.district     := row.customerDistrict;
      addressData.postalCode   := row.customerPostalCode;
      addressData.cityID       := row.customerCityID;
      addressData.cityName     := row.customerCityName;
      addressData.state        := row.customerState;
      addressData.phones       := row.customerPhones;
      
      RETURN NEXT addressData;
    END IF;
  END loop;
END
$$
LANGUAGE 'plpgsql';