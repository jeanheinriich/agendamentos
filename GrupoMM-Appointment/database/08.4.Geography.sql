-- =====================================================================
-- Geografia
-- =====================================================================
-- Armazena as informações geográficas necessárias no sistema.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Coordenadas geográficas de referência
-- ---------------------------------------------------------------------
-- Coordenadas utilizadas para referência em cálculos de distância e/ou
-- para posicionar algo num mapa.
-- ---------------------------------------------------------------------
CREATE TABLE erp.geographicCoordinates (
  geographicCoordinateID  serial,         -- ID da coordenada geográfica
  contractorID            integer         -- ID do contratante
                          NOT NULL,
  entityID                integer         -- ID da entidade na qual ela
                          NOT NULL,       -- é usada
  name                    varchar(100)    -- O nome da coordenada
                          NOT NULL,
  location                point           -- A coordenada geográfica
                          NOT NULL,
  PRIMARY KEY (geographicCoordinateID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT
);

CREATE INDEX ON erp.geographicCoordinates USING GIST(location);

INSERT INTO erp.geographicCoordinates (geographicCoordinateID, contractorID, entityID, name, location) VALUES
  ( 1, 1, 1, 'Sede Grupo M&M', point(-23.3292135,-46.7273893)),
  ( 2, 7, 7, 'Sede E. F. de Morais', point(-23.5170058, -47.5017447));

ALTER SEQUENCE erp.geographiccoordinates_geographiccoordinateid_seq RESTART WITH 3;
