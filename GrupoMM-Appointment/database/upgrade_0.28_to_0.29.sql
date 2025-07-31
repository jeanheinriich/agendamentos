-- =====================================================================
-- INCLUSÃO DO CONTROLE DE ORDENS DE SERVIÇO
-- =====================================================================
-- Esta modificação visa incluir toda a lógica para gerenciamento de
-- ordens de serviço
-- ---------------------------------------------------------------------

-- Precisamos criar tipos que definem a situação de uma ordem de serviço

-- ---------------------------------------------------------------------
-- Estado possíveis para uma ordem de serviço
-- ---------------------------------------------------------------------
CREATE TYPE OrderState AS ENUM('Registered', 'Scheduled', 'Closed',
  'ClosedWithPending', 'Cancelled');

-- ---------------------------------------------------------------------
-- Tipos de encerramentos possíveis para uma ordem de serviço
-- ---------------------------------------------------------------------
CREATE TYPE ClosingState AS ENUM('ToPerform', 'Performed', 'NotPerformed',
  'VisitNotPerformed', 'FailedVisit', 'UnproductiveVisit');

-- Criamos uma tabela para armazenar as ordens de serviço

-- ---------------------------------------------------------------------
-- Ordens de serviços
-- ---------------------------------------------------------------------
-- Contém as informações complementares para cadastro do prestador de
-- serviços, além das já armazenadas no cadastro da entidade.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.serviceOrders (
  serviceOrderID          integer           -- ID da ordem de serviço
                          NOT NULL
                          UNIQUE,
  contractorID            integer           -- ID do contratante
                          NOT NULL,
  serviceState            OrderState        -- Estado da ordem de serviço
                          NOT NULL
                          DEFAULT 'Registered',
  requestedAt             timestamp         -- Data/hora da requisição
                          NOT NULL
                          DEFAULT CURRENT_TIMESTAMP,
  vehicleID               integer           -- ID do veículo para o qual
                          NOT NULL,         -- o serviço foi agendado
  customerID              integer           -- ID do cliente ao qual
                          NOT NULL,         -- pertence o veículo
  subsidiaryID            integer           -- ID da unidade/filial na
                          NOT NULL,         -- qual está lotado o veículo
  plate                   varchar(7)        -- Placa do veículo
                          NOT NULL,
  installationID          integer           -- ID da instalação da qual
                          NOT NULL,         -- os valores serão cobrados
  customerPayerID         integer           -- ID do cliente pagador
                          NOT NULL,
  subsidiaryPayerID       integer           -- ID da unidade/filial do
                          NOT NULL,         -- cliente pagador
  vin                     char(17),         -- O número do chassi
  ownerName               varchar(100),     -- O nome do proprietário
  regionalDocumentType    integer,          -- ID do tipo do documento (Default RG)
  regionalDocumentNumber  varchar(20),      -- Número do documento
  regionalDocumentState   char(2),          -- UF onde foi emitido o documento
  nationalRegister        varchar(18)       -- CPF ou CNPJ do proprietário
                          NOT NULL
                          DEFAULT '000.000.000-00',
  phones                  text,             -- Os telefones de contato
  servicePlace            varchar(100)      -- O local onde o serviço
                          NOT NULL,         -- será executado
  serviceAddress          varchar(50),      -- O endereço do serviço
  serviceStreetNumber     varchar(10),      -- O número da casa do serviço
  serviceComplement       varchar(30),      -- O complemento do endereço
  serviceDistrict         varchar(50),      -- O bairro do serviço
  servicePostalCode       char(9),          -- O CEP do local do serviço
  serviceCityID           integer,          -- O ID da cidade do serviço
  initialDemand           text              -- Descrição do motivo que
                          NOT NULL,         -- objetiva o serviço
  foreseenServiceID       integer           -- ID do serviço previsto
                          NOT NULL,         -- para ser executado
  foreseenServiceDesc     text              -- Descrição do serviço a
                          NOT NULL,         -- ser executado pelo técnico
  scheduledTo             timestamp         -- Data/hora para qual está
                          DEFAULT NULL,     -- agendado
  serviceProviderID       integer           -- O ID do prestador
                          DEFAULT NULL,     -- responsável pela execução
  technicianID            integer           -- O ID do técnico
                          DEFAULT NULL,     -- responsável pela execução
  originPlace             varchar(100)      -- O local de origem do técnico
                          NOT NULL,         -- será executado
  originAddress           varchar(50),      -- O endereço de origem
  originStreetNumber      varchar(10),      -- O número da casa de origem
  originComplement        varchar(30),      -- O complemento do endereço
  originDistrict          varchar(50),      -- O bairro de origem
  originPostalCode        char(9),          -- O CEP de origem
  originCityID            integer,          -- O ID da cidade de origem
  actualEquipmentID       integer           -- O ID do equipamento
                          DEFAULT NULL,     -- atualmente instalado
  newEquipmentID          integer           -- O ID do novo equipamento
                          DEFAULT NULL,     -- instalado
  installationSite        varchar(100)      -- O local físico de instalação
                          DEFAULT NULL,     -- do equipamento no veículo
  blockingSite            varchar(100)      -- O local físico de instalação
                          DEFAULT NULL,     -- do bloqueio
  sirenSite               varchar(100)      -- O local físico de instalação
                          DEFAULT NULL,     -- da sirene
  panicButtonSite         varchar(100)      -- O local físico de instalação
                          DEFAULT NULL,     -- do botão de pânico
  performedAt             timestamp         -- Data/hora da execução do
                          DEFAULT NULL,     -- serviço
  performedServiceID      integer           -- ID do serviço efetivamente
                          DEFAULT NULL,     -- executado
  performedServiceDesc    text              -- Descrição do serviço
                          DEFAULT NULL,     -- executado pelo técnico
  traveledDistance        numeric(9,2)      -- Distância percorrida pelo
                          DEFAULT 0.00,     -- técnico
  serviceState            ClosingState      -- Estado da conclusão do
                          NOT NULL          -- serviço
                          DEFAULT 'ToPerform',
  pendingIssuesDesc       text              -- Descrição dos serviços
                          DEFAULT NULL,     -- ou itens pendentes
  relatedServiceOrderID   integer           -- O ID da ordem de serviço
                          DEFAULT NULL,     -- complementar
  internalNotes           text              -- Observações internas
                          DEFAULT NULL,
  createdAt               timestamp         -- A data de criação da OS
                          NOT NULL
                          DEFAULT CURRENT_TIMESTAMP,
  createdByUserID         integer           -- O ID do usuário responsável
                          NOT NULL,         -- pelo cadastro desta OS
  updatedAt               timestamp         -- A data da última modificação
                          NOT NULL          -- desta OS
                          DEFAULT CURRENT_TIMESTAMP,
  updatedByUserID         integer           -- O ID do usuário responsável
                          NOT NULL,         -- pela última modificação desta OS
  deleted                 boolean           -- O indicativo de OS removida
                          NOT NULL
                          DEFAULT false,
  deletedAt               timestamp         -- A data de remoção da OS
                          DEFAULT NULL,
  deletedByUserID         integer           -- O ID do usuário responsável
                          DEFAULT NULL,     -- pela remoção da OS
  PRIMARY KEY (serviceProviderID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (vehicleID)
    REFERENCES erp.vehicles(vehicleID)
    ON DELETE RESTRICT,
  FOREIGN KEY (customerID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (subsidiaryID)
    REFERENCES erp.subsidiaries(subsidiaryID)
    ON DELETE RESTRICT,
  FOREIGN KEY (installationID)
    REFERENCES erp.installations(installationID)
    ON DELETE RESTRICT,
  FOREIGN KEY (customerPayerID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (subsidiaryPayerID)
    REFERENCES erp.subsidiaries(subsidiaryID)
    ON DELETE RESTRICT,
  FOREIGN KEY (regionalDocumentType)
    REFERENCES erp.documentTypes(documentTypeID)
    ON DELETE CASCADE,
  FOREIGN KEY (serviceCityID)
    REFERENCES erp.cities(cityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (foreseenServiceID)
    REFERENCES erp.billingTypes(billingTypeID)
    ON DELETE RESTRICT,
  FOREIGN KEY (serviceProviderID)
    REFERENCES erp.serviceProviders(serviceProviderID)
    ON DELETE RESTRICT,
  FOREIGN KEY (technicianID)
    REFERENCES erp.technicians(technicianID)
    ON DELETE RESTRICT,
  FOREIGN KEY (originCityID)
    REFERENCES erp.cities(cityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (actualEquipmentID)
    REFERENCES erp.equipments(equipmentID)
    ON DELETE RESTRICT,
  FOREIGN KEY (newEquipmentID)
    REFERENCES erp.equipments(equipmentID)
    ON DELETE RESTRICT,
  FOREIGN KEY (performedServiceID)
    REFERENCES erp.billingTypes(billingTypeID)
    ON DELETE RESTRICT,
  FOREIGN KEY (createdByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT,
  FOREIGN KEY (updatedByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT,
  FOREIGN KEY (deletedByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- Telefones do local de origem da ordem de serviço
-- ---------------------------------------------------------------------
-- Contém as informações dos telefones do técnico e/ou do prestador de
-- serviços e/ou de outro local de onde o técnico irá partir.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.originPhones (
  originPhoneID     serial,        -- O ID do telefone
  serviceOrderID    integer        -- O ID da ordem de serviço
                    NOT NULL,
  phoneTypeID       integer        -- O ID do tipo de telefone
                    NOT NULL,
  phoneNumber       varchar(20)    -- O número do telefone
                    NOT NULL,
  PRIMARY KEY (originPhoneID),
  FOREIGN KEY (serviceOrder)
    REFERENCES erp.serviceOrders(serviceOrderID)
    ON DELETE CASCADE,
  FOREIGN KEY (phoneTypeID)
    REFERENCES erp.phoneTypes(phoneTypeID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- Telefones do local do serviço descrito na ordem de serviço
-- ---------------------------------------------------------------------
-- Contém as informações dos telefones do cliente, do proprietário e/ou
-- do local onde o veículo estará no momento da execução do serviço
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.servicePhones (
  servicePhoneID    serial,        -- O ID do telefone
  serviceOrderID    integer        -- O ID da ordem de serviço
                    NOT NULL,
  phoneTypeID       integer        -- O ID do tipo de telefone
                    NOT NULL,
  phoneNumber       varchar(20)    -- O número do telefone
                    NOT NULL,
  PRIMARY KEY (servicePhoneID),
  FOREIGN KEY (serviceOrder)
    REFERENCES erp.serviceOrders(serviceOrderID)
    ON DELETE CASCADE,
  FOREIGN KEY (phoneTypeID)
    REFERENCES erp.phoneTypes(phoneTypeID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- Documentos pertencentes à uma ordem de serviço
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.serviceOrderAttachments (
  serviceOrderAttachmentID  serial,       -- ID do anexo
  serviceOrderID            integer       -- O ID da ordem de serviço
                            NOT NULL,
  realFilename              varchar(100)  -- Nome do arquivo original
                            NOT NULL,
  filename                  varchar(30)   -- Nome do arquivo real usado no
                            NOT NULL,     -- armazenamento
  PRIMARY KEY (serviceOrderAttachmentID),
  FOREIGN KEY (serviceOrder)
    REFERENCES erp.serviceOrders(serviceOrderID)
    ON DELETE CASCADE
);


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

-- ---------------------------------------------------------------------
-- Endereços do técnico
-- ---------------------------------------------------------------------
-- Recupera os endereços disponíveis para o local onde um técnico pode
-- encontrar-se em função dos endereços disponíveis no cadastro
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.getTechnicianAddresses(
  FcontractorID integer, FtechnicianID integer)
RETURNS SETOF erp.addressData AS
$$
DECLARE
  addressData  erp.addressData%rowtype;
  row  record;
  rowNumber  integer;
BEGIN
  IF (FcontractorID IS NULL) THEN
    FcontractorID := 0;
  END IF;
  IF (FtechnicianID IS NULL) THEN
    FtechnicianID := 0;
  END IF;

  -- Monta a consulta
  FOR row IN
    SELECT serviceProvider.name AS serviceProviderName,
           serviceProvider.entityTypeID,
           technician.technicianIsTheProvider,
           unity.genderID AS serviceProviderGenderID,
           unity.address AS serviceProviderAddress,
           unity.streetNumber AS serviceProviderStreetNumber,
           unity.complement AS serviceProviderComplement,
           unity.district AS serviceProviderDistrict,
           unity.postalCode AS serviceProviderPostalCode,
           unity.cityID AS serviceProviderCityID,
           serviceProviderCity.name AS serviceProviderCityName,
           serviceProviderCity.state AS serviceProviderState,
           (SELECT jsonb_agg(jsonb_build_object('phoneTypeID', phoneTypeID, 'phoneNumber', phoneNumber) ORDER BY phoneID)
              FROM erp.phones
             WHERE subsidiaryID = unity.subsidiaryID) AS serviceProviderPhones,
           technician.name AS technicianName,
           technician.address AS technicianAddress,
           technician.streetNumber AS technicianStreetNumber,
           technician.complement AS technicianComplement,
           technician.district AS technicianDistrict,
           technician.postalCode AS technicianPostalCode,
           technician.cityID AS technicianCityID,
           technicianCity.name AS technicianCityName,
           technicianCity.state AS technicianState,
           CASE
             WHEN NOT technician.technicianIsTheProvider THEN
               (SELECT jsonb_agg(jsonb_build_object('phoneTypeID', phoneTypeID, 'phoneNumber', phoneNumber) ORDER BY technicianPhoneID)
                  FROM erp.technicianPhones
                 WHERE technicianID = technician.technicianID)
             ELSE '[]'::jsonb
           END AS technicianPhones,
           technician.genderID
      FROM erp.technicians AS technician
     INNER JOIN erp.cities AS technicianCity ON (technician.cityID = technicianCity.cityID)
     INNER JOIN erp.entities AS serviceProvider ON (technician.serviceProviderID = serviceProvider.entityID)
     INNER JOIN erp.subsidiaries AS unity ON (serviceProvider.entityID = unity.entityID AND unity.headOffice = true)
     INNER JOIN erp.cities AS serviceProviderCity ON (unity.cityID = serviceProviderCity.cityID)
     WHERE technician.contractorID = FcontractorID
       AND technician.technicianID = FtechnicianID
  LOOP
    rowNumber := 1;

    IF NOT row.technicianIsTheProvider THEN
      RAISE NOTICE 'Retornando os dados do técnico.';
      addressData.rowNumber := rowNumber;
      addressData.main      := true;

      -- Lida com as questões de gênero
      CASE (row.genderID) 
        WHEN 3 THEN
          -- Feminino
          addressData.place  := LEFT('Na técnica ' || row.technicianName, 100);
        WHEN 2 THEN
          -- Masculino
          addressData.place  := LEFT('No técnico ' || row.technicianName, 100);
        ELSE
          addressData.place  := LEFT('No(a) técnico ' || row.technicianName, 100);
      END CASE;

      addressData.address      := row.technicianAddress;
      addressData.streetNumber := row.technicianStreetNumber;
      addressData.complement   := row.technicianComplement;
      addressData.district     := row.technicianDistrict;
      addressData.postalCode   := row.technicianPostalCode;
      addressData.cityID       := row.technicianCityID;
      addressData.cityName     := row.technicianCityName;
      addressData.state        := row.technicianState;
      addressData.phones       := row.technicianPhones;

      RETURN NEXT addressData;

      rowNumber := rowNumber + 1;
      addressData.rowNumber    := rowNumber;
      addressData.main         := false;
    ELSE
      addressData.rowNumber    := rowNumber;
      addressData.main         := true;
    END IF;

    -- Retornamos o endereço do prestador de serviços sempre
    
    -- Lida com as questões de gênero
    RAISE NOTICE 'Retornando os dados do prestador de serviços.';
    IF (row.entityTypeID = 2) THEN
      CASE (row.serviceProviderGenderID) 
        WHEN 3 THEN
          -- Feminino
          addressData.place  := LEFT('Na prestadora de serviços ' || row.serviceProviderName, 100);
        WHEN 2 THEN
          -- Masculino
          addressData.place  := LEFT('No prestador de serviços ' || row.serviceProviderName, 100);
        ELSE
          addressData.place  := LEFT('No(a) prestador(a) de serviços ' || row.serviceProviderName, 100);
      END CASE;
    ELSE
      addressData.place      := LEFT('Na sede do prestador de serviços ' || row.serviceProviderName, 100);
    END IF;
    addressData.address      := row.serviceProviderAddress;
    addressData.streetNumber := row.serviceProviderStreetNumber;
    addressData.complement   := row.serviceProviderComplement;
    addressData.district     := row.serviceProviderDistrict;
    addressData.postalCode   := row.serviceProviderPostalCode;
    addressData.cityID       := row.serviceProviderCityID;
    addressData.cityName     := row.serviceProviderCityName;
    addressData.state        := row.serviceProviderState;
    addressData.phones       := row.serviceProviderPhones;
    
    RETURN NEXT addressData;
  END loop;
END
$$
LANGUAGE 'plpgsql';