-- =====================================================================
-- MODIFICAÇÃO DO MODELO DE EQUIPAMENTO
-- =====================================================================
-- Esta modificação visa incluir uma maneira de determinar o tamanho do
-- número de série de um equipamento. O número de série não possui uma
-- padronização, podendo variar de modelo e de fabricante. Os zeros à
-- esquerda são suprimidos e este campo serve para que o sistema possa
-- lidar corratamente com isto em caso de precisar enviar o código com
-- os zeros à esquerda, como em API's de terceiros e/ou na comunicação
-- com o próprio equipamento.
-- ---------------------------------------------------------------------

-- Alteramos a tabela de modelos de equipamentos, acrescentando um campo
-- para indicar o tamanho do número de série
ALTER TABLE erp.equipmentModels
  ADD COLUMN serialNumberSize integer;

-- Incluímos uma função que nos permite formatar o número de série com
-- zeros à esquerda

-- ---------------------------------------------------------------------
-- Obtém o número de série de um equipamento formatado
-- ---------------------------------------------------------------------
-- Obtém o número de série de um equipamento acrescido dos respectivos
-- zeros à esquerda, de forma a ter o tamanho exigido pelo fabricante.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.getFormattedSerialNumber(integer)
RETURNS text AS
$BODY$
  SELECT LPAD(equipment.serialNumber, model.serialNumberSize, '0')
    FROM erp.equipments AS equipment
   INNER JOIN erp.equipmentModels AS model USING (equipmentModelID)
   WHERE equipment.equipmentID = $1;
$BODY$
LANGUAGE 'sql' IMMUTABLE STRICT;