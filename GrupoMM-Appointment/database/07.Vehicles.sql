-- =====================================================================
-- Veículos
-- =====================================================================
-- Tabelas utilizada no controle de veículos
-- =====================================================================

-- ---------------------------------------------------------------------
-- Marcas de Veículos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.vehicleBrands (
  vehicleBrandID  serial,       -- ID da marca do veículo
  contractorID    integer       -- ID do contratante
                  NOT NULL,
  name            varchar(30)   -- Marca do veículo
                  NOT NULL,
  fipeName        varchar(30),  -- Nome da marca na Fipe
  PRIMARY KEY (vehicleBrandID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT
);

INSERT INTO erp.vehicleBrands (vehicleBrandID, contractorID, name, fipeName) VALUES
  (1, 1, 'Acura', 'Acura'),
  (2, 1, 'Adly', 'Adly'),
  (3, 1, 'Agrale', 'Agrale'),
  (4, 1, 'Alfa Romeo', 'Alfa Romeo'),
  (5, 1, 'Amazonas', 'Amazonas'),
  (6, 1, 'AM Gen', 'AM Gen'),
  (7, 1, 'Aprilia', 'Aprilia'),
  (8, 1, 'Asia Motors', 'Asia Motors'),
  (9, 1, 'Aston Martin', 'Aston Martin'),
  (10, 1, 'Atala', 'Atala'),
  (11, 1, 'Audi', 'Audi'),
  (12, 1, 'Bajaj', 'Bajaj'),
  (13, 1, 'Benelli', 'Benelli'),
  (14, 1, 'Beta', 'Beta'),
  (15, 1, 'Bimota', 'Bimota'),
  (16, 1, 'BMW', 'BMW'),
  (17, 1, 'Brandy', 'Brandy'),
  (18, 1, 'Brava', 'Brava'),
  (19, 1, 'BRM', 'BRM'),
  (20, 1, 'BRP', 'BRP'),
  (21, 1, 'Buell', 'Buell'),
  (22, 1, 'Bueno', 'Bueno'),
  (23, 1, 'Buggy', 'Buggy'),
  (24, 1, 'Bugre', 'Bugre'),
  (25, 1, 'Bycristo', 'Bycristo'),
  (26, 1, 'Cadillac', 'Cadillac'),
  (27, 1, 'Cagiva', 'Cagiva'),
  (28, 1, 'Caloi', 'Caloi'),
  (29, 1, 'CBT Jipe', 'CBT Jipe'),
  (30, 1, 'Chana', 'Chana'),
  (31, 1, 'Changan', 'Changan'),
  (32, 1, 'Chery', 'Chery'),
  (33, 1, 'Chevrolet GM', 'GM - Chevrolet'),
  (34, 1, 'Chrysler', 'Chrysler'),
  (35, 1, 'Ciccobus', 'Ciccobus'),
  (36, 1, 'Citroen', 'Citroen'),
  (37, 1, 'Cross Lander', 'Cross Lander'),
  (38, 1, 'Daelim', 'Daelim'),
  (39, 1, 'Daewoo', 'Daewoo'),
  (40, 1, 'DAF', 'DAF'),
  (41, 1, 'Dafra', 'Dafra'),
  (42, 1, 'Daihatsu', 'Daihatsu'),
  (43, 1, 'Dayang', 'Dayang'),
  (44, 1, 'Dayun', 'Dayun'),
  (45, 1, 'Derbi', 'Derbi'),
  (46, 1, 'Dodge', 'Dodge'),
  (47, 1, 'Ducati', 'Ducati'),
  (48, 1, 'Effa', 'Effa'),
  (49, 1, 'Effa-JMC', 'Effa-JMC'),
  (50, 1, 'Emme', 'Emme'),
  (51, 1, 'Engesa', 'Engesa'),
  (52, 1, 'Envemo', 'Envemo'),
  (53, 1, 'Ferrari', 'Ferrari'),
  (54, 1, 'Fiat', 'Fiat'),
  (55, 1, 'Fibravan', 'Fibravan'),
  (56, 1, 'Ford', 'Ford'),
  (57, 1, 'Foton', 'Foton'),
  (58, 1, 'Fox', 'Fox'),
  (59, 1, 'Fyber', 'Fyber'),
  (60, 1, 'Fym', 'Fym'),
  (61, 1, 'Garinni', 'Garinni'),
  (62, 1, 'Gas Gas', 'Gas Gas'),
  (63, 1, 'Geely', 'Geely'),
  (64, 1, 'GMC', 'GMC'),
  (65, 1, 'Great Wall', 'Great Wall'),
  (66, 1, 'Green', 'Green'),
  (67, 1, 'Gurgel', 'Gurgel'),
  (68, 1, 'Hafei', 'Hafei'),
  (69, 1, 'Haobao', 'Haobao'),
  (70, 1, 'Harley-Davidson', 'Harley-Davidson'),
  (71, 1, 'Hartford', 'Hartford'),
  (72, 1, 'Hero', 'Hero'),
  (73, 1, 'Honda', 'Honda'),
  (74, 1, 'Husaberg', 'Husaberg'),
  (75, 1, 'Husqvarna', 'Husqvarna'),
  (76, 1, 'Hyundai', 'Hyundai'),
  (77, 1, 'Indian', 'Indian'),
  (78, 1, 'Iros', 'Iros'),
  (79, 1, 'Isuzu', 'Isuzu'),
  (80, 1, 'Iveco', 'Iveco'),
  (81, 1, 'Jac Motors', 'Jac'),
  (82, 1, 'Jaguar', 'Jaguar'),
  (83, 1, 'Jeep', 'Jeep'),
  (84, 1, 'Jiapeng Volcano', 'Jiapeng Volcano'),
  (85, 1, 'Jinbei', 'Jinbei'),
  (86, 1, 'Johnnypag', 'Johnnypag'),
  (87, 1, 'Jonny', 'Jonny'),
  (88, 1, 'JPX', 'JPX'),
  (89, 1, 'Kahena', 'Kahena'),
  (90, 1, 'Kasinski', 'Kasinski'),
  (91, 1, 'Kawasaki', 'Kawasaki'),
  (92, 1, 'Kia Motors', 'Kia Motors'),
  (93, 1, 'Kimco', 'Kimco'),
  (94, 1, 'KTM', 'KTM'),
  (95, 1, 'Lada', 'Lada'),
  (96, 1, 'Lamborghini', 'Lamborghini'),
  (97, 1, 'Land Rover', 'Land Rover'),
  (98, 1, 'Landum', 'Landum'),
  (99, 1, 'L''Aquila', 'L''Aquila'),
  (100, 1, 'Lavrale', 'Lavrale'),
  (101, 1, 'Lerivo', 'Lerivo'),
  (102, 1, 'Lexus', 'Lexus'),
  (103, 1, 'Lifan', 'Lifan'),
  (104, 1, 'Lobini', 'Lobini'),
  (105, 1, 'Lon-V', 'Lon-V'),
  (106, 1, 'Lotus', 'Lotus'),
  (107, 1, 'Magrão Triciclos', 'Magrão Triciclos'),
  (108, 1, 'Mahindra', 'Mahindra'),
  (109, 1, 'Malaguti', 'Malaguti'),
  (110, 1, 'MAN', 'MAN'),
  (111, 1, 'Marcopolo', 'Marcopolo'),
  (112, 1, 'Mascarello', 'Mascarello'),
  (113, 1, 'Maserati', 'Maserati'),
  (114, 1, 'Matra', 'Matra'),
  (115, 1, 'Maxibus', 'Maxibus'),
  (116, 1, 'Mazda', 'Mazda'),
  (117, 1, 'Mercedes-benz', 'Mercedes-benz'),
  (118, 1, 'Mercury', 'Mercury'),
  (119, 1, 'MG', 'MG'),
  (120, 1, 'Mini', 'Mini'),
  (121, 1, 'Mitsubishi', 'Mitsubishi'),
  (122, 1, 'Miura', 'Miura'),
  (123, 1, 'Miza', 'Miza'),
  (124, 1, 'Motocar', 'Motocar'),
  (125, 1, 'Moto Guzzi', 'Moto Guzzi'),
  (126, 1, 'Motorino', 'Motorino'),
  (127, 1, 'MRX', 'MRX'),
  (128, 1, 'MV Agusta', 'MV Agusta'),
  (129, 1, 'MVK', 'MVK'),
  (130, 1, 'Navistar', 'Navistar'),
  (131, 1, 'Neobus', 'Neobus'),
  (132, 1, 'Nissan', 'Nissan'),
  (133, 1, 'Orca', 'Orca'),
  (134, 1, 'Pegassi', 'Pegassi'),
  (135, 1, 'Peugeot', 'Peugeot'),
  (136, 1, 'Piaggio', 'Piaggio'),
  (137, 1, 'Plymouth', 'Plymouth'),
  (138, 1, 'Pontiac', 'Pontiac'),
  (139, 1, 'Porsche', 'Porsche'),
  (140, 1, 'Puma-Alfa', 'Puma-Alfa'),
  (141, 1, 'RAM', 'RAM'),
  (142, 1, 'Regal Raptor', 'Regal Raptor'),
  (143, 1, 'Rely', 'Rely'),
  (144, 1, 'Renault', 'Renault'),
  (145, 1, 'Riguete', 'Riguete'),
  (146, 1, 'Rolls-Royce', 'Rolls-Royce'),
  (147, 1, 'Rover', 'Rover'),
  (148, 1, 'Royal Enfield', 'Royal Enfield'),
  (149, 1, 'SAAB', 'SAAB'),
  (150, 1, 'SAAB-Scania', 'SAAB-Scania'),
  (151, 1, 'Sanyang', 'Sanyang'),
  (152, 1, 'Saturn', 'Saturn'),
  (153, 1, 'Scania', 'Scania'),
  (154, 1, 'Seat', 'Seat'),
  (155, 1, 'Shacman', 'Shacman'),
  (156, 1, 'Shineray', 'Shineray'),
  (157, 1, 'Siamoto', 'Siamoto'),
  (158, 1, 'Sinotruk', 'Sinotruk'),
  (159, 1, 'Smart', 'Smart'),
  (160, 1, 'Ssangyong', 'Ssangyong'),
  (161, 1, 'Subaru', 'Subaru'),
  (162, 1, 'Sundown', 'Sundown'),
  (163, 1, 'Suzuki', 'Suzuki'),
  (164, 1, 'Tac', 'Tac'),
  (165, 1, 'Targos', 'Targos'),
  (166, 1, 'Tiger', 'Tiger'),
  (167, 1, 'Toyota', 'Toyota'),
  (168, 1, 'Traxx', 'Traxx'),
  (169, 1, 'Triumph', 'Triumph'),
  (170, 1, 'Troller', 'Troller'),
  (171, 1, 'Vento', 'Vento'),
  (172, 1, 'VolksWagen', 'VolksWagen'),
  (173, 1, 'Volvo', 'Volvo'),
  (174, 1, 'Wake', 'Wake'),
  (175, 1, 'Walk', 'Walk'),
  (176, 1, 'Walkbus', 'Walkbus'),
  (177, 1, 'Wuyang', 'Wuyang'),
  (178, 1, 'Yamaha', 'Yamaha');

ALTER SEQUENCE erp.vehiclebrands_vehiclebrandid_seq RESTART WITH 179;

-- ---------------------------------------------------------------------
-- Tipos de veículos por marca
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.vehicleTypesPerBrands (
  vehicleTypePerBrandID   serial,   -- ID do tipo de veículo por marca
  contractorID            integer   -- ID do contratante
                          NOT NULL,
  vehicleBrandID          integer   -- ID da marca do veículo
                          NOT NULL,
  vehicleTypeID           integer   -- ID do tipo do veículo
                          NOT NULL,
  fipeID                  integer   -- Código da marca na Fipe
                          NOT NULL,
  PRIMARY KEY (vehicleTypePerBrandID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (vehicleBrandID)
    REFERENCES erp.vehicleBrands(vehicleBrandID)
    ON DELETE CASCADE,
  FOREIGN KEY (vehicleTypeID)
    REFERENCES erp.vehicleTypes(vehicleTypeID)
    ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- Sincronismo de tipos de veículos fabricados por marca
-- ---------------------------------------------------------------------
-- Stored Procedure que atualiza a associação de marcas de veículos para
-- fabricar um determinado tipo de veículo
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.synchronizeVehicleTypePerBrand(fContractorID integer,
  fBrandID integer, fVehicleTypeID integer, fName varchar(30), fID integer)
RETURNS void AS
$$
DECLARE
  vehicleTypePerBrandData record;
BEGIN
  -- Verifica se foi fornecido o ID da marca
  -- RAISE NOTICE 'fBrandID (%)', fBrandID;
  IF fBrandID = 0 THEN
    -- Localiza primeiramente a marca pelo ID da Fipe e tipo de veículo
    EXECUTE 'SELECT vehicleBrandID FROM erp.vehicleTypesPerBrands WHERE vehicleTypeID = $1 AND fipeID = $2 AND contractorID = $3'
       INTO fBrandID
      USING fVehicleTypeID, fID, fContractorID;
    IF (fBrandID IS NULL) THEN
      -- Caso não tenha localizado, tenta novamente utilizando o nome da
      -- marca fornecido
      EXECUTE 'SELECT vehicleBrandID FROM erp.vehicleBrands WHERE public.unaccented(fipeName) ILIKE public.unaccented($1) AND contractorID = $2'
         INTO fBrandID
        USING fName, fContractorID;
      IF (fBrandID IS NULL) THEN
        -- Caso não tenha localizado, adiciona a nova marca
        INSERT INTO erp.vehicleBrands
                   (contractorID, name, fipeName)
             VALUES (fContractorID, fName, fName)
        RETURNING vehicleBrandID INTO fBrandID;
      END IF;

      -- Agora tenta localizar se a marca está associada ao tipo de veículo
      -- indicado
      FOR vehicleTypePerBrandData IN
        SELECT vehicleTypePerBrandID,
               fipeID
          FROM erp.vehicleTypesPerBrands
         WHERE vehicleBrandID = fBrandID
           AND vehicleTypesPerBrands.contractorID =  fContractorID
           AND vehicleTypeID = fVehicleTypeID loop
        IF vehicleTypePerBrandData.fipeID <> fID THEN
          -- Atualiza o código Fipe para este tipo de veículo desta marca
          UPDATE erp.vehicleTypesPerBrands
             SET fipeID = fID
           WHERE vehicleTypePerBrandID = vehicleTypePerBrandData.vehicleTypePerBrandID
             AND contractorID = fContractorID;
        END IF;
      END loop;
      IF NOT FOUND THEN
        -- Informa que esta marca fabrica o tipo de veículo em questão
        INSERT INTO erp.vehicleTypesPerBrands
                   (contractorID, vehicleBrandID, vehicleTypeID, fipeID)
             VALUES (fContractorID, fBrandID, fVehicleTypeID, fID);
        -- RAISE NOTICE 'Inserted FipeID (%)', fID;
      END IF;
    END IF;
  END IF;
END
$$
LANGUAGE 'plpgsql';

-- Ex: Sincronizar os veículos da marca Acura
-- SELECT synchronizeVehicleTypePerBrand(0, 1, 'Acura', 1);

-- ---------------------------------------------------------------------
-- Dados de tipos de veículos por marca
-- ---------------------------------------------------------------------
-- Stored Procedure que recupera os tipos de veículos fabricados por uma
-- determinada marca
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.getVehiclesTypesFromBrandID(fContractorID integer,
  fbrandID integer)
RETURNS text AS
$$
DECLARE
  typesOnGroup    record;
  typesResult     text;
  count           integer;
BEGIN
  count = 0;
  FOR typesOnGroup IN
    SELECT vehicleTypesPerBrands.vehicleTypeID,
           vehicleTypes.name
      FROM erp.vehicleTypesPerBrands
     INNER JOIN erp.vehicleTypes USING (vehicleTypeID)
     WHERE vehicleTypesPerBrands.vehicleBrandID = fbrandID
       AND vehicleTypesPerBrands.contractorID = fContractorID
     ORDER BY vehicleTypeID loop
    -- RAISE NOTICE 'FOUND (%) rows', count;
    IF count > 0 THEN
      typesResult = concat(typesResult, ', ', typesOnGroup.name);
    ELSE
      typesResult = concat(typesResult, typesOnGroup.name);
    END IF;
    count = count + 1;
  END loop;
  IF NOT FOUND THEN
    typesResult = 'Sem uso';
    -- RAISE NOTICE 'NOT FOUND rows';
  END IF;
  RETURN typesResult;
END
$$
LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Modelos de Veículos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.vehicleModels (
  vehicleModelID          serial,       -- ID do modelo do veículo
  contractorID            integer       -- ID do contratante
                          NOT NULL,
  vehicleTypePerBrandID   integer       -- ID do tipo de veículo por
                          NOT NULL,     -- marca
  vehicleTypeID           integer       -- ID do tipo do veículo
                          NOT NULL,
  vehicleSubtypeID        integer       -- ID do subtipo do veículo
                          DEFAULT NULL,
  name                    varchar(50)   -- Modelo do veículo
                          NOT NULL,
  fipeID                  integer       -- Código do modelo na Fipe
                          NOT NULL,
  PRIMARY KEY (vehicleModelID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (vehicleTypePerBrandID)
    REFERENCES erp.vehicleTypesPerBrands(vehicleTypePerBrandID)
    ON DELETE CASCADE,
  FOREIGN KEY (vehicleTypeID)
    REFERENCES erp.vehicleTypes(vehicleTypeID)
    ON DELETE CASCADE,
  FOREIGN KEY (vehicleSubtypeID)
    REFERENCES erp.vehicleSubtypes(vehicleSubtypeID)
    ON DELETE CASCADE
);

CREATE INDEX vehicleModelByName ON erp.vehicleModels (contractorID, vehicleTypeID, name);

-- ---------------------------------------------------------------------
-- Sincronismo de modelos de veículos
-- ---------------------------------------------------------------------
-- Stored Procedure que atualiza os modelos de veículos
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.synchronizeVehicleModel(fContractorID integer,
  fVehicleTypeID integer, brandFipeID integer, fName varchar(30), fID integer)
RETURNS void AS
$$
DECLARE
  fVehicleModelID   integer;
  typePerBrandID    integer;
BEGIN
  -- Verifica se foi fornecido o ID do modelo
  IF fID > 0 THEN
    -- Tenta lozalizar o modelo pelo ID dele e o da marca na Fipe
    EXECUTE 'SELECT vehicleModelID FROM erp.vehicleModels INNER JOIN vehicleTypesPerBrands USING (vehicleTypePerBrandID) WHERE vehicleModels.fipeID= $1 AND vehicleTypesPerBrands.vehicleTypeID = $2 AND vehicleTypesPerBrands.fipeID = $3 AND vehicleTypesPerBrands.contractorID = $4'
       INTO fVehicleModelID
      USING fID, fVehicleTypeID, brandFipeID, fContractorID;
    -- RAISE NOTICE 'fVehicleModelID = %', fVehicleModelID;
    IF (fVehicleModelID IS NULL) THEN
      -- Caso não tenha localizado, localiza o código da marca para o
      -- tipo de veículo
      -- RAISE NOTICE 'Localizando marca...';
      EXECUTE 'SELECT vehicleTypePerBrandID FROM erp.vehicleTypesPerBrands WHERE vehicleTypeID = $1 AND fipeID = $2 AND contractorID = $3'
         INTO typePerBrandID
        USING fVehicleTypeID, brandFipeID, fContractorID;
      -- RAISE NOTICE 'typePerBrandID = %', typePerBrandID;
      IF (typePerBrandID > 0) THEN
        -- Caso tenha localizado, adiciona o novo modelo
        INSERT INTO erp.vehicleModels
                   (contractorID, vehicleTypePerBrandID, vehicleTypeID, name, fipeID)
             VALUES (fContractorID, typePerBrandID, fVehicleTypeID, fName, fID)
          RETURNING vehicleModelID INTO fVehicleModelID;
      END IF;
    ELSE
      -- Apenas atualiza o modelo
      UPDATE erp.vehicleModels
         SET name = fName
       WHERE vehicleModelID = fVehicleModelID;
    END IF;
  END IF;
END
$$
LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Veículos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.vehicles (
  vehicleID               serial,       -- ID do veículo
  contractorID            integer       -- ID do contratante
                          NOT NULL,
  customerID              integer       -- Número de identificação do cliente ao
                          NOT NULL,     -- qual pertence o cadastro
  subsidiaryID            integer       -- Número de identificação da unidade/filial
                          NOT NULL,     -- na qual está lotado o veículo
  plate                   varchar(7)    -- Placa do veículo
                          NOT NULL,
  denomination            varchar(10)   -- Denominação do veículo (
                          NOT NULL      -- também conhecido como apelido)
                          DEFAULT '',
  vehicleTypeID           integer       -- ID do tipo do veículo
                          NOT NULL,
  vehicleBrandID          integer       -- ID da marca do veículo
                          NOT NULL,
  vehicleModelID          integer       -- ID do modelo do veículo
                          NOT NULL,
  yearFabr                char(4)       -- O ano de fabricação
                          NOT NULL,
  yearModel               char(4)       -- O ano do modelo
                          NOT NULL,
  vehicleColorID          integer       -- O ID da cor predominante do veículo
                          NOT NULL,
  carNumber               varchar(20),  -- O nº da frota/carro dentro do cliente
  fuelType                char(1)       -- O combustível do veículo
                          NOT NULL,
  renavam                 char(11)      -- O número do RENAVAM
                          NOT NULL,
  vin                     char(17),     -- O número do chassi
                                        --   VIN: Vehicle Identification Number
  customerIsTheOwner      boolean       -- O indicativo de que o cliente
                          DEFAULT true, -- é o proprietário do veículo
  ownerName               varchar(100), -- O nome do proprietário
  regionalDocumentType    integer,      -- ID do tipo do documento (Default RG)
  regionalDocumentNumber  varchar(20),  -- Número do documento (Ex: RG)
  regionalDocumentState   char(2),      -- O estado (UF) onde foi emitido o documento
  nationalRegister        varchar(18)   -- CPF ou CNPJ
                          NOT NULL
                          DEFAULT '000.000.000-00',
  address                 varchar(50),  -- O endereço do proprietário
  streetNumber            varchar(10),  -- O número da casa do proprietário
  complement              varchar(30),  -- O complemento do endereço do proprietário
  district                varchar(50),  -- O bairro
  postalCode              char(9),      -- O CEP
  cityID                  integer,      -- O ID da cidade
  email                   varchar(100), -- Email principal do proprietário
  phoneNumber             varchar(20),  -- Telefone principal do proprietário
  customerPayerID         integer       -- Número de identificação do
                          NOT NULL,     -- cliente pagador
  subsidiaryPayerID       integer       -- Número de identificação da
                          NOT NULL,     -- unidade/filial pagadora
  atSameCustomerAddress   boolean       -- O indicativo de que o veículo
                          DEFAULT true, -- permanece no endereço do cliente
  atSameOwnerAddress      boolean       -- O indicativo de que o veículo
                          DEFAULT false,-- permanece no endereço do proprietário
  atAnotherAddress        boolean       -- O indicativo de que o veículo
                          DEFAULT false,-- permanece em outro endereço
  anotherName             varchar(100), -- O nome do endereço alternativo
  anotherAddress          varchar(50),  -- O endereço alternativo
  anotherStreetNumber     varchar(10),  -- O número da casa do endereço alternativo
  anotherComplement       varchar(30),  -- O complemento do endereço alternativo
  anotherDistrict         varchar(50),  -- O bairro do endereço alternativo
  anotherPostalCode       char(9),      -- O CEP do endereço alternativo
  anotherCityID           integer,      -- O ID da cidade do endereço alternativo
  note                    text,         -- Um campo de observação
  monitored               boolean       -- O indicativo de veículo sendo
                          NOT NULL      -- monitorado
                          DEFAULT FALSE,
  blocked                 boolean       -- O indicativo de veículo bloqueado
                          NOT NULL
                          DEFAULT FALSE,
  blockNotices            boolean       -- O indicativo de que avisos
                          DEFAULT false,-- não devem ser enviados
  blockedDays             integer       -- Por quandos dias deve durar o
                          DEFAULT NULL, -- bloqueio dos avisos (0 indeterminado)
  remainingDays           integer       -- Quandos dias faltam para acabar
                          DEFAULT 0,    -- o bloqueio dos avisos
  blockedStartAt          date          -- Data do início do bloqueio
                          DEFAULT NULL, -- dos avisos
  blockedEndAt            date          -- Data de término do bloqueio
                          DEFAULT NULL, -- dos avisos
  createdAt               timestamp     -- A data de criação do veículo
                          NOT NULL
                          DEFAULT CURRENT_TIMESTAMP,
  createdByUserID         integer       -- O ID do usuário responsável pelo
                          NOT NULL,     -- cadastro deste veículo
  updatedAt               timestamp     -- A data da última modificação
                          NOT NULL      -- deste veículo
                          DEFAULT CURRENT_TIMESTAMP,
  updatedByUserID         integer       -- O ID do usuário responsável pela
                          NOT NULL,     -- última modificação deste veículo
  deleted                 boolean       -- O indicativo de veículo removido
                          NOT NULL
                          DEFAULT false,
  deletedAt               timestamp     -- A data de remoção do veículo
                          DEFAULT NULL,
  deletedByUserID         integer       -- O ID do usuário responsável pela
                          DEFAULT NULL, -- remoção do veículo
  PRIMARY KEY (vehicleID),              -- O indice primário
  CHECK (XOR(atSameCustomerAddress, atSameOwnerAddress)
     OR XOR(atSameOwnerAddress, atAnotherAddress)),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (customerID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (subsidiaryID)
    REFERENCES erp.subsidiaries(subsidiaryID)
    ON DELETE RESTRICT,
  FOREIGN KEY (cityID)
    REFERENCES erp.cities(cityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (vehicleTypeID)
    REFERENCES erp.vehicleTypes(vehicleTypeID)
    ON DELETE RESTRICT,
  FOREIGN KEY (vehicleBrandID)
    REFERENCES erp.vehicleBrands(vehicleBrandID)
    ON DELETE RESTRICT,
  FOREIGN KEY (vehicleModelID)
    REFERENCES erp.vehicleModels(vehicleModelID)
    ON DELETE RESTRICT,
  FOREIGN KEY (vehicleColorID)
    REFERENCES erp.vehicleColors(vehicleColorID)
    ON DELETE RESTRICT,
  FOREIGN KEY (customerPayerID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (subsidiaryPayerID)
    REFERENCES erp.subsidiaries(subsidiaryID)
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

CREATE INDEX idx_vehicles_composite
    ON erp.vehicles(vehicleID, vehicleTypeID, vehicleBrandID, vehicleModelID, vehicleColorID);

-- ---------------------------------------------------------------------
-- Documentos pertencentes à um veículo
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.vehicleAttachments (
  vehicleAttachmentID   serial,       -- Número de identificação do anexo
  contractorID          integer       -- ID do contratante
                        NOT NULL,
  vehicleID             integer       -- Número de identificação do veículo
                        NOT NULL,
  realFilename          varchar(100)  -- Nome do arquivo original
                        NOT NULL,
  filename              varchar(30)   -- Nome do arquivo real usado no
                        NOT NULL,     -- armazenamento
  PRIMARY KEY (vehicleAttachmentID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (vehicleID)
    REFERENCES erp.vehicles(vehicleID)
    ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- Telefones do proprietário de um veículo
-- ---------------------------------------------------------------------
-- Contém as informações dos telefones do proprietário de um veículo
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.ownerPhones (
  ownerPhoneID  serial,        -- O ID do telefone
  vehicleID     integer        -- O ID do veículo
                NOT NULL,
  phoneTypeID   integer        -- O ID do tipo de telefone
                NOT NULL,
  phoneNumber   varchar(20)    -- O número do telefone
                NOT NULL,
  PRIMARY KEY (ownerPhoneID),
  FOREIGN KEY (vehicleID)
    REFERENCES erp.vehicles(vehicleID)
    ON DELETE CASCADE,
  FOREIGN KEY (phoneTypeID)
    REFERENCES erp.phoneTypes(phoneTypeID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- Telefones do endereço onde o veículo permanece
-- ---------------------------------------------------------------------
-- Contém as informações dos telefones do local onde o veículo permanece
-- a maior parte do tempo, e que não é o mesmo do cliente ou o do
-- proprietário do veículo
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.anotherPhones (
  anotherPhoneID  serial,        -- O ID do telefone
  vehicleID       integer        -- O ID do veículo
                  NOT NULL,
  phoneTypeID     integer        -- O ID do tipo de telefone
                  NOT NULL,
  phoneNumber     varchar(20)    -- O número do telefone
                  NOT NULL,
  PRIMARY KEY (anotherPhoneID),
  FOREIGN KEY (vehicleID)
    REFERENCES erp.vehicles(vehicleID)
    ON DELETE CASCADE,
  FOREIGN KEY (phoneTypeID)
    REFERENCES erp.phoneTypes(phoneTypeID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- Dados de veículos
-- ---------------------------------------------------------------------
-- Cria uma stored procedure que recupera as informações de veículos
-- para o gerenciamento de veículos
-- ---------------------------------------------------------------------
CREATE TYPE erp.vehicleData AS
(
  customerID         integer,
  subsidiaryID       integer,
  associationID      integer,
  associationUnityID integer,
  hasMonitoring      boolean,
  juridicalperson    boolean,
  cooperative        boolean,
  headOffice         boolean,
  type               smallint,
  level              smallint,
  active             boolean,
  activeAssociation  boolean,
  name               varchar(100),
  tradingName        varchar(100),
  ownername          varchar(100),
  monitored          boolean,
  blocked            boolean,
  vehicleID          integer,
  vehicleTypeID      integer,
  vehicleTypeName    varchar(30),
  vehicleSubtypeID   integer,
  vehicleSubtypeName varchar(30),
  vehicleBrandID     integer,
  vehicleBrandName   varchar(30),
  vehicleModelID     integer,
  vehicleModelName   varchar(50),
  vehicleColorID     integer,
  vehicleColorName   varchar(30),
  vehicleColor       varchar(30),
  carNumber          varchar(20),
  fuelType           char(1),
  fuelTypeName       varchar(30),
  withoutMainTracker boolean,
  blockedLevel       smallint,
  createdAt          timestamp,
  fullcount          integer
);

CREATE OR REPLACE FUNCTION erp.getVehiclesData(FcontractorID integer,
  FcustomerID integer, FsubsidiaryID integer, FvehicleID integer,
  FsearchValue varchar(100), FsearchField varchar(20), Factive boolean,
  FOrder varchar, Fstatus integer, Ftype integer,
  Skip integer, LimitOf integer)
RETURNS SETOF erp.vehicleData AS
$$
DECLARE
  vehicleData  erp.vehicleData%rowtype;
  row          record;
  query        varchar;
  field        varchar;
  filter       varchar;
  typeFilter  varchar;
  limits       varchar;
  blockedLevel integer;
  lastEntityID  integer;
  lastSubsidiaryID  integer;
  lastEntityPayerID  integer;
  lastSubsidiaryPayerID  integer;
BEGIN
  IF (FcontractorID IS NULL) THEN
    FcontractorID = 0;
  END IF;
  IF (FcustomerID IS NULL) THEN
    FcustomerID = 0;
  END IF;
  IF (FsubsidiaryID IS NULL) THEN
    FsubsidiaryID = 0;
  END IF;
  IF (FvehicleID IS NULL) THEN
    FvehicleID = 0;
  END IF;
  IF (FOrder IS NULL) THEN
    FOrder = 'cooperative ASC, itemName, itemOrder, customerName, headOffice DESC, subsidiaryName, plate';
  END IF;
  IF (Fstatus IS NULL) THEN
    Fstatus := 0;
  END IF;
  IF (Ftype IS NULL) THEN
    Ftype := 0;
  END IF;
  IF (LimitOf > 0) THEN
    limits := format('LIMIT %s OFFSET %s',
                     LimitOf, Skip);
  ELSE
    limits := '';
  END IF;
  filter := '';

  -- Os estados possíveis são: (1) inativo e (2) ativo
  typeFilter := '';
  IF (Fstatus > 0) THEN
    IF (Fstatus = 1) THEN
      typeFilter := ' AND (customerPayerID IS NULL AND amountOfEquipmentsOnVehicle = 0)';
    ELSE
      typeFilter := ' AND ((customerPayerID IS NOT NULL) OR (customerPayerID IS NULL AND amountOfEquipmentsOnVehicle > 0))';
    END IF;
  END IF;
  
  IF (FvehicleID > 0) THEN
    -- Realiza a filtragem por veículo
    typeFilter := typeFilter || format(' AND vehicleID = %s', FvehicleID);
  ELSE
    IF (FcustomerID > 0) THEN
      -- Realiza a filtragem por cliente
      typeFilter := typeFilter || format(' AND ( (itemID = %s) OR (customerID = %s))', FcustomerID, FcustomerID);
      IF (FsubsidiaryID > 0) THEN
        typeFilter := typeFilter ||
          format(' AND ((itemUnityID = %s) OR (subsidiaryID = %s))', FsubsidiaryID, FsubsidiaryID);
      END IF;
    END IF;
  END IF;

  IF (FsearchValue IS NULL) THEN
    -- RAISE NOTICE 'FsearchValue IS NULL';
  ELSE
    -- RAISE NOTICE 'FsearchValue IS NOT NULL';
    IF (FsearchValue = '') THEN
      -- RAISE NOTICE 'FsearchValue IS EMPTY';
    ELSE
      IF (FsearchField = 'plate') THEN
        filter := format(' AND ((vehicle.plate ILIKE ''%%%s%%'') OR (vehicle.plate ILIKE ''%%'' || public.getPlateVariant(''%s'') || ''%%''))',
                         FsearchValue, FsearchValue);
      ELSE
        -- Determina o campo onde será realizada a pesquisa
        CASE (FsearchField)
          WHEN 'vehicleBrandName' THEN
            field := 'vehicleBrand.name';
          WHEN 'vehicleModelName' THEN
            field := 'vehicleModel.name';
          ELSE
            field := 'vehicle.' || FsearchField;
        END CASE;

        -- Monta o filtro
        filter := format(' AND public.unaccented(%s) ILIKE public.unaccented(''%%%s%%'')',
                         field, FsearchValue);
      END IF;
    END IF;
  END IF;
  -- RAISE NOTICE 'filter IS %', filter;

  IF (Factive IS NOT NULL) THEN
    IF (Factive = TRUE) THEN
      -- Adiciona a opção de filtragem de veículos ativos
      filter := filter || ' AND vehicle.blocked = false';
    ELSE
      -- Adiciona a opção de filtragem de veículos inativos
      filter := filter || ' AND vehicle.blocked = true';
    END IF;
  END IF;

  -- Monta a consulta
  query := format(
    'WITH DataSet AS (
      SELECT CASE
               WHEN equipment.customerPayerID IS NOT NULL
               AND vehicle.customerID <> equipment.customerPayerID
               THEN equipment.customerPayerID
               ELSE vehicle.customerID
             END AS itemID,
             CASE
               WHEN equipment.customerPayerID IS NOT NULL
               AND vehicle.customerID <> equipment.customerPayerID
               THEN customerPayer.blocked
               ELSE customer.blocked
             END AS itemBlocked,
             CASE
               WHEN equipment.customerPayerID IS NOT NULL
               AND vehicle.customerID <> equipment.customerPayerID
               THEN customerPayer.name
               ELSE customer.name
             END AS itemName,
             CASE
               WHEN equipment.customerPayerID IS NOT NULL
               AND vehicle.customerID <> equipment.customerPayerID
               THEN customerPayer.tradingName
               ELSE customer.tradingName
             END AS itemTradingName,
             CASE
               WHEN equipment.customerPayerID IS NOT NULL
               AND vehicle.subsidiaryID <> equipment.subsidiaryID
               THEN unityPayer.subsidiaryID
               ELSE unity.subsidiaryID
             END AS itemUnityID,
             CASE
               WHEN equipment.customerPayerID IS NOT NULL
               AND vehicle.subsidiaryID <> equipment.subsidiaryID
               THEN unityPayer.name
               ELSE unity.name
             END AS itemUnity,
             CASE
               WHEN equipment.customerPayerID IS NOT NULL
               AND vehicle.subsidiaryID <> equipment.subsidiaryID
               THEN unityPayer.headOffice
               ELSE unity.headOffice
             END AS itemUnityHeadOffice,
             CASE
               WHEN equipment.customerPayerID IS NOT NULL
               AND vehicle.subsidiaryID <> equipment.subsidiaryID
               THEN unityPayer.blocked
               ELSE unity.blocked
             END AS itemUnityBlocked,
             CASE
               WHEN equipment.customerPayerID IS NOT NULL
               AND vehicle.customerID <> equipment.customerPayerID
               THEN customerPayerType.cooperative
               ELSE customerType.cooperative
             END AS cooperative,
             CASE
               WHEN equipment.customerPayerID IS NOT NULL
               AND vehicle.customerID <> equipment.customerPayerID
               THEN customerPayerType.juridicalperson
               ELSE customerType.juridicalperson
             END AS juridicalpersonOfItem,
             CASE
               WHEN equipment.customerPayerID IS NOT NULL
               AND vehicle.customerID = equipment.customerPayerID
               THEN 1
               WHEN equipment.customerPayerID IS NOT NULL
               AND vehicle.customerID <> equipment.customerPayerID
               THEN 3
               ELSE 2
             END AS itemOrder,
             CASE
               WHEN equipment.customerPayerID IS NOT NULL
               THEN customerPayer.enableatMonitoring
               ELSE FALSE
             END AS hasMonitoring,
             vehicle.customerID,
             customer.name AS customerName,
             customer.tradingName,
             customer.blocked AS customerBlocked,
             customerType.juridicalperson,
             unity.name AS subsidiaryName,
             unity.blocked AS subsidiaryBlocked,
             unity.headOffice,
             vehicle.subsidiaryID,
             vehicle.vehicleID AS id,
             vehicle.plate,
             vehicle.vehicleTypeID,
             vehicle.vehicleBrandID,
             vehicle.vehicleModelID,
             vehicle.vehicleColorID,
             vehicle.carNumber,
             vehicle.fuelType,
             CASE
               WHEN equipment.customerPayerID IS NOT NULL AND customerPayer.enableatmonitoring
               THEN vehicle.monitored
               ELSE false
             END AS monitored,
             vehicle.blocked AS vehicleBlocked,
             vehicle.createdAt,
             equipment.customerPayerID
        FROM erp.vehicles AS vehicle
       INNER JOIN erp.entities AS customer ON (vehicle.customerID = customer.entityID)
       INNER JOIN erp.subsidiaries AS unity ON (vehicle.subsidiaryID = unity.subsidiaryID)
       INNER JOIN erp.entitiesTypes AS customerType ON (customer.entityTypeID = customerType.entityTypeID)
        LEFT JOIN erp.equipments AS equipment ON (vehicle.vehicleID = equipment.vehicleID AND equipment.main = true)
        LEFT JOIN erp.entities AS customerPayer ON (equipment.customerPayerID = customerPayer.entityID)
        LEFT JOIN erp.entitiesTypes AS customerPayerType ON (customerPayer.entityTypeID = customerPayerType.entityTypeID)
        LEFT JOIN erp.subsidiaries AS unityPayer ON (equipment.subsidiaryPayerID = unityPayer.subsidiaryID)
       WHERE vehicle.contractorID = %s
         AND vehicle.deleted = false %s
       ),
       EquipmentCount AS (
         SELECT E.vehicleID AS id,
                COUNT(*) AS amountOfEquipmentsOnVehicle
         FROM erp.equipments AS E
         GROUP BY E.vehicleID
       ),
       items AS (
         SELECT DataSet.itemID,
                DataSet.itemBlocked,
                DataSet.itemName,
                DataSet.itemTradingName,
                DataSet.itemUnityID,
                DataSet.itemUnity,
                DataSet.itemUnityHeadOffice,
                DataSet.itemUnityBlocked,
                DataSet.cooperative,
                DataSet.juridicalpersonOfItem,
                DataSet.itemOrder,
                DataSet.hasMonitoring,
                DataSet.customerID,
                DataSet.customerName,
                DataSet.tradingName,
                DataSet.customerBlocked,
                DataSet.juridicalperson,
                DataSet.subsidiaryName,
                DataSet.subsidiaryBlocked,
                DataSet.headOffice,
                DataSet.subsidiaryID,
                DataSet.subsidiaryBlocked,
                DataSet.id,
                DataSet.plate,
                DataSet.vehicleTypeID,
                type.name AS vehicleTypeName,
                CASE
                  WHEN model.vehicleSubtypeID IS NULL THEN 0
                  ELSE model.vehicleSubtypeID
                END AS vehicleSubtypeID,
                CASE
                  WHEN model.vehicleSubtypeID IS NULL THEN ''Não informado''
                  ELSE subtype.name
                END AS vehicleSubtypeName,
                DataSet.vehicleBrandID,
                brand.name AS vehicleBrandName,
                DataSet.vehicleModelID,
                model.name AS vehicleModelName,
                DataSet.vehicleColorID,
                color.name AS vehicleColorName,
                color.color AS vehicleColor,
                DataSet.carNumber,
                DataSet.fuelType,
                fuel.name AS fuelTypeName,
                DataSet.monitored,
                DataSet.vehicleBlocked,
                DataSet.createdAt,
                EquipmentCount.amountOfEquipmentsOnVehicle,
                DataSet.customerPayerID
           FROM DataSet
          INNER JOIN erp.vehicleTypes AS type ON (DataSet.vehicleTypeID = type.vehicleTypeID)
          INNER JOIN erp.vehicleBrands AS brand ON (DataSet.vehicleBrandID = brand.vehicleBrandID)
          INNER JOIN erp.vehicleModels AS model ON (DataSet.vehicleModelID = model.vehicleModelID)
           LEFT JOIN erp.vehicleSubtypes AS subtype ON (model.vehicleSubtypeID = subtype.vehicleSubtypeID)
          INNER JOIN erp.vehicleColors AS color USING (vehicleColorID)
          INNER JOIN erp.fuelTypes AS fuel USING (fuelType)
           LEFT JOIN EquipmentCount ON (DataSet.id = EquipmentCount.id)
       ) SELECT *,
                (SELECT count(*) FROM erp.subsidiaries AS S WHERE S.entityID = itemID) AS unityItems,
                (SELECT count(*) FROM erp.subsidiaries AS S WHERE S.entityID = customerID) AS unityCustomerItems,
                CASE
                  WHEN customerPayerID IS NOT NULL THEN true
                  WHEN customerPayerID IS NULL AND amountOfEquipmentsOnVehicle > 0 THEN true
                  ELSE false
                END AS active,
                CASE
                  WHEN customerPayerID IS NOT NULL THEN false
                  WHEN customerPayerID IS NULL AND amountOfEquipmentsOnVehicle > 0 THEN true
                  ELSE false
                END AS withoutMainTracker,
                count(*) OVER() AS fullcount
           FROM items
          WHERE (1=1) %s
          ORDER BY %s %s;',
    fContractorID, filter, typeFilter, FOrder, limits
  );
  -- RAISE NOTICE 'SQL: %',query;

  lastEntityPayerID := 0;
  lastSubsidiaryPayerID := 0;
  lastEntityID := 0;
  lastSubsidiaryID := 0;

  FOR row IN EXECUTE query
  LOOP
    -- RAISE NOTICE 'lastEntityPayerID = %', lastEntityPayerID;
    -- RAISE NOTICE 'itemID = %', row.itemID;
    IF (lastEntityPayerID <> row.itemID) THEN
      -- Iniciamos um novo grupo
      lastEntityPayerID := row.itemID;
      lastEntityID := row.itemID;

      -- Verifica se precisamos dividir esta entidade em mais de uma
      -- linha
      -- RAISE NOTICE 'unityItems = %', row.unityItems;
      IF (row.unityItems > 1) THEN
        -- Descrevemos aqui a entidade principal (cliente)
        vehicleData.customerID         := row.itemID;
        vehicleData.subsidiaryID       := 0;
        vehicleData.associationID      := NULL;
        vehicleData.associationUnityID := NULL;
        vehicleData.hasMonitoring      := row.hasMonitoring;
        vehicleData.juridicalperson    := row.juridicalpersonOfItem;
        vehicleData.cooperative        := row.cooperative;
        vehicleData.headOffice         := false;
        vehicleData.type               := 1;
        vehicleData.level              := 0;
        vehicleData.active             := NOT row.itemBlocked;
        vehicleData.activeAssociation  := NOT row.itemBlocked;
        vehicleData.name               := row.itemName;
        vehicleData.tradingName        := row.itemTradingName;
        vehicleData.ownerName          := NULL;
        vehicleData.blocked            := row.itemBlocked;
        vehicleData.vehicleID          := NULL;
        vehicleData.vehicleTypeID      := NULL;
        vehicleData.vehicleTypeName    := NULL;
        vehicleData.vehicleSubtypeID   := NULL;
        vehicleData.vehicleSubtypeName := NULL;
        vehicleData.vehicleBrandID     := NULL;
        vehicleData.vehicleBrandName   := NULL;
        vehicleData.vehicleModelID     := NULL;
        vehicleData.vehicleModelName   := NULL;
        vehicleData.vehicleColorID     := NULL;
        vehicleData.vehicleColorName   := NULL;
        vehicleData.vehicleColor       := NULL;
        vehicleData.carNumber          := NULL;
        vehicleData.fuelType           := NULL;
        vehicleData.fuelTypeName       := NULL;
        vehicleData.monitored          := NULL;

        IF (row.itemBlocked) THEN
          vehicleData.blockedLevel     := 1;
        ELSE
          vehicleData.blockedLevel     := 0;
        END IF;
        vehicleData.withoutMainTracker := false;
        vehicleData.createdAt          := row.createdAt;
        vehicleData.fullcount          := row.fullcount;

        RETURN NEXT vehicleData;
      END IF;
    END IF;

    -- RAISE NOTICE 'lastSubsidiaryPayerID = %', lastSubsidiaryPayerID;
    -- RAISE NOTICE 'itemUnityID = %', row.itemUnityID;
    IF (lastSubsidiaryPayerID <> row.itemUnityID) THEN
      -- Iniciamos um novo subgrupo (unidade/filial do cliente)
      lastSubsidiaryPayerID := row.itemUnityID;
      lastSubsidiaryID := row.itemUnityID;

      -- Informa os dados da unidade (ou do cliente se houver uma
      -- unidade apenas)
      vehicleData.customerID         := row.itemID;
      vehicleData.subsidiaryID       := row.itemUnityID;
      vehicleData.associationID      := NULL;
      vehicleData.associationUnityID := NULL;
      vehicleData.hasMonitoring      := row.hasMonitoring;
      vehicleData.juridicalperson    := row.juridicalpersonOfItem;
      vehicleData.cooperative        := row.cooperative;
      vehicleData.headOffice         := row.itemUnityHeadOffice;
      vehicleData.type               := 1;
      IF (row.unityItems > 1) THEN
        vehicleData.level            := 1;
      ELSE
        vehicleData.level            := 2;
      END IF;
      vehicleData.active             := NOT(row.itemBlocked AND row.itemUnityBlocked);
      vehicleData.activeAssociation  := NOT row.itemBlocked;
      IF (row.unityItems > 1) THEN
        vehicleData.name             := row.itemUnity;
        vehicleData.tradingName      := '';
        vehicleData.blocked          := row.itemUnityBlocked;
      ELSE
        vehicleData.name             := row.itemName;
        vehicleData.tradingName      := row.itemTradingName;
        vehicleData.blocked          := row.itemBlocked;
      END IF;
      vehicleData.ownerName          := NULL;
      vehicleData.vehicleID          := NULL;
      vehicleData.vehicleTypeID      := NULL;
      vehicleData.vehicleTypeName    := NULL;
      vehicleData.vehicleSubtypeID   := NULL;
      vehicleData.vehicleSubtypeName := NULL;
      vehicleData.vehicleBrandID     := NULL;
      vehicleData.vehicleBrandName   := NULL;
      vehicleData.vehicleModelID     := NULL;
      vehicleData.vehicleModelName   := NULL;
      vehicleData.vehicleColorID     := NULL;
      vehicleData.vehicleColorName   := NULL;
      vehicleData.vehicleColor       := NULL;
      vehicleData.carNumber          := NULL;
      vehicleData.fuelType           := NULL;
      vehicleData.fuelTypeName       := NULL;
      vehicleData.monitored          := NULL;

      IF (row.itemBlocked) THEN
        vehicleData.blockedLevel     := 1;
      ELSE
        IF (row.itemUnityBlocked) THEN
          vehicleData.blockedLevel   := 2;
        ELSE
          vehicleData.blockedLevel   := 0;
        END IF;
      END IF;
      vehicleData.withoutMainTracker := false;
      vehicleData.createdAt          := row.createdAt;
      vehicleData.fullcount          := row.fullcount;

      RETURN NEXT vehicleData;
    END IF;

    IF (lastEntityID <> row.customerID) THEN
      -- Iniciamos um novo grupo
      lastEntityID := row.customerID;

      -- Verifica se precisamos dividir esta entidade em mais de uma
      -- linha
      IF (row.unityCustomerItems > 1) THEN
        -- Descrevemos aqui a entidade secundária
        vehicleData.customerID           := row.customerID;
        vehicleData.subsidiaryID         := 0;
        IF (row.cooperative) THEN
          vehicleData.associationID      := row.itemID;
          vehicleData.associationUnityID := row.itemUnityID;
        ELSE
          vehicleData.associationID      := NULL;
          vehicleData.associationUnityID := NULL;
        END IF;
        vehicleData.hasMonitoring        := row.hasMonitoring;
        vehicleData.juridicalperson      := row.juridicalperson;
        vehicleData.cooperative          := false;
        vehicleData.headOffice           := false;
        vehicleData.type                 := 2;
        vehicleData.level                := 3;
        vehicleData.active               := NOT row.customerBlocked;
        vehicleData.activeAssociation    := NOT row.itemBlocked;
        vehicleData.name                 := row.customerName;
        vehicleData.tradingName          := row.tradingName;
        vehicleData.ownerName            := NULL;
        vehicleData.blocked              := row.customerBlocked;
        vehicleData.vehicleID            := NULL;
        vehicleData.vehicleTypeID        := NULL;
        vehicleData.vehicleTypeName      := NULL;
        vehicleData.vehicleSubtypeID     := NULL;
        vehicleData.vehicleSubtypeName   := NULL;
        vehicleData.vehicleBrandID       := NULL;
        vehicleData.vehicleBrandName     := NULL;
        vehicleData.vehicleModelID       := NULL;
        vehicleData.vehicleModelName     := NULL;
        vehicleData.vehicleColorID       := NULL;
        vehicleData.vehicleColorName     := NULL;
        vehicleData.vehicleColor         := NULL;
        vehicleData.carNumber            := NULL;
        vehicleData.fuelType             := NULL;
        vehicleData.fuelTypeName         := NULL;
        vehicleData.monitored            := NULL;

        IF (row.itemID = row.customerID) THEN
          IF (row.itemBlocked) THEN
            vehicleData.blockedLevel     := 1;
          ELSE
            IF (row.itemUnityBlocked) THEN
              vehicleData.blockedLevel   := 2;
            ELSE
              vehicleData.blockedLevel   := 0;
            END IF;
          END IF;
        ELSE
          IF (row.itemBlocked) THEN
            vehicleData.blockedLevel     := 1;
          ELSE
            IF (row.itemUnityBlocked) THEN
              vehicleData.blockedLevel   := 2;
            ELSE
              IF (row.customerBlocked) THEN
                vehicleData.blockedLevel := 3;
              ELSE
                vehicleData.blockedLevel := 0;
              END IF;
            END IF;
          END IF;
        END IF;

        vehicleData.withoutMainTracker   := false;
        vehicleData.createdAt            := row.createdAt;
        vehicleData.fullcount            := row.fullcount;

        RETURN NEXT vehicleData;
      END IF;
    END IF;

    IF (lastSubsidiaryID <> row.subsidiaryID) THEN
      -- Iniciamos um novo subgrupo
      lastSubsidiaryID := row.subsidiaryID;

      -- Informa os dados da unidade (ou do cliente se houver uma
      -- unidade apenas) da entidade secundária
      vehicleData.customerID             := row.customerID;
      vehicleData.subsidiaryID           := row.subsidiaryID;
      IF (row.cooperative) THEN
        vehicleData.associationID        := row.itemID;
        vehicleData.associationUnityID   := row.itemUnityID;
      ELSE
        vehicleData.associationID        := NULL;
        vehicleData.associationUnityID   := NULL;
      END IF;
      vehicleData.hasMonitoring          := row.hasMonitoring;
      vehicleData.juridicalperson        := row.juridicalperson;
      vehicleData.cooperative            := false;
      vehicleData.headOffice             := row.headOffice;
      vehicleData.type                   := 2;
      IF (row.unityCustomerItems > 1) THEN
        vehicleData.level                := 4;
      ELSE
        vehicleData.level                := 5;
      END IF;
      vehicleData.active                 := NOT(row.customerBlocked AND row.subsidiaryBlocked);
      vehicleData.activeAssociation      := NOT row.itemBlocked;
      IF (row.unityCustomerItems > 1) THEN
        vehicleData.name                   := row.subsidiaryName;
        vehicleData.tradingName            := '';
        vehicleData.blocked                := row.subsidiaryBlocked;
      ELSE
        vehicleData.name                   := row.customerName;
        vehicleData.tradingName            := row.tradingName;
        vehicleData.blocked                := row.customerBlocked;
      END IF;
      vehicleData.ownerName              := NULL;
      vehicleData.vehicleID              := NULL;
      vehicleData.vehicleTypeID          := NULL;
      vehicleData.vehicleTypeName        := NULL;
      vehicleData.vehicleSubtypeID       := NULL;
      vehicleData.vehicleSubtypeName     := NULL;
      vehicleData.vehicleBrandID         := NULL;
      vehicleData.vehicleBrandName       := NULL;
      vehicleData.vehicleModelID         := NULL;
      vehicleData.vehicleModelName       := NULL;
      vehicleData.vehicleColorID         := NULL;
      vehicleData.vehicleColorName       := NULL;
      vehicleData.vehicleColor           := NULL;
      vehicleData.carNumber              := NULL;
      vehicleData.fuelType               := NULL;
      vehicleData.fuelTypeName           := NULL;
      vehicleData.monitored              := NULL;

      IF ( (row.itemID = row.customerID) OR
           (row.itemUnityID = row.subsidiaryID) ) THEN
        IF (row.itemBlocked) THEN
          vehicleData.blockedLevel       := 1;
        ELSE
          IF (row.itemUnityBlocked) THEN
            vehicleData.blockedLevel     := 2;
          ELSE
            vehicleData.blockedLevel     := 0;
          END IF;
        END IF;
      ELSE
        IF (row.itemBlocked) THEN
          vehicleData.blockedLevel       := 1;
        ELSE
          IF (row.itemUnityBlocked) THEN
            vehicleData.blockedLevel     := 2;
          ELSE
            IF (row.customerBlocked) THEN
              vehicleData.blockedLevel   := 3;
            ELSE
              IF (row.subsidiaryBlocked) THEN
                vehicleData.blockedLevel := 4;
              ELSE
                vehicleData.blockedLevel := 0;
              END IF;
            END IF;
          END IF;
        END IF;
      END IF;

      vehicleData.withoutMainTracker     := false;
      vehicleData.createdAt              := row.createdAt;
      vehicleData.fullcount              := row.fullcount;

      RETURN NEXT vehicleData;
    END IF;

    -- Informa os dados do veículo
    vehicleData.customerID               := row.customerID;
    vehicleData.subsidiaryID             := row.subsidiaryID;
    IF (row.cooperative) THEN
      vehicleData.associationID          := row.itemID;
      vehicleData.associationUnityID     := row.itemUnityID;
    ELSE
      vehicleData.associationID          := NULL;
      vehicleData.associationUnityID     := NULL;
    END IF;
    vehicleData.hasMonitoring            := row.hasMonitoring;
    vehicleData.juridicalperson          := row.juridicalperson;
    vehicleData.cooperative              := false;
    vehicleData.headOffice               := row.headOffice;
    vehicleData.type                     := 3;
    vehicleData.level                    := 6;
    vehicleData.active                   := row.active;
    vehicleData.activeAssociation        := NOT row.itemBlocked;
    vehicleData.name                     := row.plate;
    vehicleData.tradingName              := NULL;
    vehicleData.ownerName                := row.customerName;
    vehicleData.blocked                  := row.vehicleBlocked;
    vehicleData.vehicleID                := row.id;
    vehicleData.vehicleTypeID            := row.vehicleTypeID;
    vehicleData.vehicleTypeName          := row.vehicleTypeName;
    vehicleData.vehicleSubtypeID         := row.vehicleSubtypeID;
    vehicleData.vehicleSubtypeName       := row.vehicleSubtypeName;
    vehicleData.vehicleBrandID           := row.vehicleBrandID;
    vehicleData.vehicleBrandName         := row.vehicleBrandName;
    vehicleData.vehicleModelID           := row.vehicleModelID;
    vehicleData.vehicleModelName         := row.vehicleModelName;
    vehicleData.vehicleColorID           := row.vehicleColorID;
    vehicleData.vehicleColorName         := row.vehicleColorName;
    vehicleData.vehicleColor             := row.vehicleColor;
    vehicleData.carNumber                := row.carNumber;
    vehicleData.fuelType                 := row.fuelType;
    vehicleData.fuelTypeName             := row.fuelTypeName;
    vehicleData.monitored                := row.monitored;

    -- Determina o nível de bloqueio do veículo
    IF ( (row.itemID = row.customerID) OR
         (row.itemUnityID = row.subsidiaryID) ) THEN
      IF (row.itemBlocked) THEN
        vehicleData.blockedLevel         := 1;
      ELSE
        IF (row.itemUnityBlocked) THEN
          vehicleData.blockedLevel       := 2;
        ELSE
          IF (row.vehicleBlocked) THEN
            vehicleData.blockedLevel     := 5;
          ELSE
            vehicleData.blockedLevel     := 0;
          END IF;
        END IF;
      END IF;
    ELSE
      IF (row.itemBlocked) THEN
        vehicleData.blockedLevel         := 1;
      ELSE
        IF (row.itemUnityBlocked) THEN
          vehicleData.blockedLevel       := 2;
        ELSE
          IF (row.customerBlocked) THEN
            vehicleData.blockedLevel     := 3;
          ELSE
            IF (row.subsidiaryBlocked) THEN
              vehicleData.blockedLevel   := 4;
            ELSE
              IF (row.vehicleBlocked) THEN
                vehicleData.blockedLevel := 5;
              ELSE
                vehicleData.blockedLevel := 0;
              END IF;
            END IF;
          END IF;
        END IF;
      END IF;
    END IF;

    vehicleData.withoutMainTracker       := row.withoutMainTracker;
    vehicleData.createdAt                := row.createdAt;
    vehicleData.fullcount                := row.fullcount;

    RETURN NEXT vehicleData;
  END loop;
END
$$
LANGUAGE 'plpgsql';

-- SELECT * FROM erp.getVehiclesData(1, 0, 0, 0, '', 'plate', NULL, NULL, 0, 0, 0, 0);

-- ---------------------------------------------------------------------
-- Autorizações por veículo/equipamento
-- ---------------------------------------------------------------------
-- Contém as informações de autorização de veículos e do respectivo
-- equipamento que um determinado usuário autônomo pode acessar
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.authorizedEquipments (
  authorizedEquipmentID   serial,       -- ID da autorização
  contractorID            integer       -- ID do contratante
                          NOT NULL,
  userID                  integer       -- ID do usuário autorizado a
                          NOT NULL,     -- acessar o equipamento
  equipmentID             integer       -- Número de identificação do
                          NOT NULL,     -- equipamento autorizado
  vehicleID               integer       -- Número de identificação do
                          NOT NULL,     -- veículo autorizado
  PRIMARY KEY (authorizedEquipmentID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (userID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT,
  FOREIGN KEY (equipmentID)
    REFERENCES erp.equipments(equipmentID)
    ON DELETE CASCADE,
  FOREIGN KEY (vehicleID)
    REFERENCES erp.vehicles(vehicleID)
    ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- Equipamentos para obtenção de histórico externamente
-- ---------------------------------------------------------------------
-- Contém as informações de equipamentos para os quais precisamos obter
-- o histórico externamente em um provedor
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.equipmentsToGetHistory (
  historyID     serial,       -- ID do histórico
  contractorID  integer       -- ID do contratante
                NOT NULL,
  equipmentID   integer       -- Número de identificação do
                NOT NULL,     -- equipamento
  platform      varchar(20)   -- Plataforma do provedor
                NOT NULL,
  PRIMARY KEY (historyID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (equipmentID)
    REFERENCES erp.equipments(equipmentID)
    ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- Veículos visíveis
-- ---------------------------------------------------------------------
-- Contém as informações de veículos que um usuário pode visualizar
-- quando o mesmo for de uma subconta de um cliente
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.visibleVehicles (
  visibleVehicleID  serial,       -- ID do veículo visível
  contractorID      integer       -- ID do contratante
                    NOT NULL,
  userID            integer       -- ID do usuário
                    NOT NULL,
  vehicleID         integer       -- ID do veículo a ser exibido
                    NOT NULL,
  PRIMARY KEY (visibleVehicleID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (userID)
    REFERENCES erp.users(userID)
    ON DELETE CASCADE,
  FOREIGN KEY (vehicleID)
    REFERENCES erp.vehicles(vehicleID)
    ON DELETE CASCADE
);
