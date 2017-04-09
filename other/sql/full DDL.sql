CREATE TABLE tiporegimen
(
    tr_id INTEGER PRIMARY KEY NOT NULL,
    tr_descrp VARCHAR(50) NOT NULL
);

CREATE TABLE escalaprogre
(
    ep_id INTEGER PRIMARY KEY NOT NULL,
    ep_tipogrv INTEGER NOT NULL,
    ep_rangomin NUMERIC(18,2) NOT NULL,
    ep_rangomax NUMERIC(18,2) NOT NULL,
    ep_impuesto NUMERIC(3,2) NOT NULL,
    ep_tipor INTEGER NOT NULL,
    CONSTRAINT escalaprogre_tiporegimen_tr_id_fk FOREIGN KEY (ep_tipor) REFERENCES tiporegimen (tr_id)
);
COMMENT ON COLUMN escalaprogre.ep_tipogrv IS 'Tipo de Gravamen';
COMMENT ON COLUMN escalaprogre.ep_rangomin IS 'Monto minimo sobre el que aplica';
COMMENT ON COLUMN escalaprogre.ep_rangomax IS 'Monto max sobre el que aplica';
COMMENT ON COLUMN escalaprogre.ep_impuesto IS 'Porcentaje de deduccion';
COMMENT ON COLUMN escalaprogre.ep_tipor IS 'Tipo de regimen sobre el que afecta';

CREATE TABLE tipoperso
(
    tp_id INTEGER PRIMARY KEY NOT NULL,
    tp_descr VARCHAR(50) NOT NULL
);

/*Actualizado 09 Mar 17 Agregada llave de cifrado*/
CREATE TABLE persona
(
  per_id VARCHAR(20) PRIMARY KEY NOT NULL,
  per_nom VARCHAR(100) NOT NULL,
  per_ced VARCHAR(12) NOT NULL,
  per_tipoper INTEGER NOT NULL,
  per_sign_key VARCHAR(2000) NOT NULL,
  per_cipher_key VARCHAR(1000) NOT NULL,
  per_actividad VARCHAR(150) NOT NULL,
  per_tiporeg INTEGER NOT NULL,
  CONSTRAINT persona_tipoperso_tp_id_fk FOREIGN KEY (per_tipoper) REFERENCES tipoperso (tp_id),
  CONSTRAINT persona_tiporegimen_tr_id_fk FOREIGN KEY (per_tiporeg) REFERENCES tiporegimen (tr_id)
);
COMMENT ON COLUMN persona.per_nom IS 'Nombre de la persona/sociedad';
CREATE UNIQUE INDEX persona_per_ced_uindex ON persona (per_ced);
CREATE UNIQUE INDEX persona_per_crypto_uindex ON persona (per_sign_key);

CREATE TABLE contacto
(
    ct_idper VARCHAR(20) PRIMARY KEY NOT NULL,
    ct_tipo VARCHAR(4) NOT NULL,
    ct_valor VARCHAR(50) NOT NULL,
    CONSTRAINT contacto_persona_per_id_fk FOREIGN KEY (ct_idper) REFERENCES persona (per_id)
);

CREATE TABLE perfisica
(
    pf_id VARCHAR(20) PRIMARY KEY NOT NULL,
    pf_nom VARCHAR(50) NOT NULL,
    pf_ape VARCHAR(50) NOT NULL,
    pf_2ape VARCHAR(50),
    pf_fechnac DATE NOT NULL,
    CONSTRAINT perfisica_persona_per_id_fk FOREIGN KEY (pf_id) REFERENCES persona (per_id)
);
COMMENT ON COLUMN perfisica.pf_nom IS 'Dato Fisico eg. Jose';
COMMENT ON COLUMN perfisica.pf_ape IS 'Dato Fisico eg. Figueres';

/*Actualizado 08 Mar 17*/
CREATE TABLE sucursal
(
  sc_cedid VARCHAR(12) NOT NULL,
  sc_scid INTEGER NOT NULL,
  sc_dir VARCHAR(100) NOT NULL,
  CONSTRAINT sucursal_persona_per_ced_fk FOREIGN KEY (sc_cedid) REFERENCES persona (per_ced)
);
COMMENT ON COLUMN sucursal.sc_dir IS 'Direccion Fisica del local';
CREATE UNIQUE INDEX sucursal_sc_scid_sc_cedid_pk ON sucursal (sc_scid, sc_cedid);

/*Actualizado 08 Mar 17*/
CREATE TABLE ventas
(
  vt_cedid VARCHAR(12) NOT NULL,
  vt_scid INTEGER NOT NULL,
  vt_ordid VARCHAR(50) PRIMARY KEY NOT NULL,
  vt_fecha TIMESTAMP NOT NULL,
  vt_xml XML NOT NULL,
  vt_recibido XML,
  CONSTRAINT ventas_sucursal_sc_scid_sc_cedid_fk FOREIGN KEY (vt_scid, vt_cedid) REFERENCES sucursal (sc_scid, sc_cedid)
);
COMMENT ON COLUMN ventas.vt_ordid IS 'RES. 14-10-16';
COMMENT ON COLUMN ventas.vt_recibido IS 'ACUSE DE RECIBO';

/* FUNCTIONS */
/*Validation of lengths - last mod Apr 8 2017*/
CREATE OR REPLACE FUNCTION sp_fst_validation(
  clave VARCHAR(50),
  idEmisor VARCHAR(12),
  idReceptor VARCHAR(12),
  tmstmp VARCHAR,
  idSucursal INT
) RETURNS VARCHAR(6) AS $passed$
BEGIN
  IF NOT EXISTS(SELECT FROM ventas WHERE vt_ordid = clave)
     AND EXISTS(SELECT FROM persona WHERE per_ced = idEmisor)
     AND EXISTS(SELECT FROM persona WHERE per_ced = idReceptor)
     AND is_timestamp(tmstmp)
     AND EXISTS(SELECT FROM sucursal WHERE sc_cedid = idEmisor AND sc_scid = idSucursal)
  THEN
    RETURN 'PASSED';
  ELSE
    RETURN 'NO';
  END IF;
END;
$passed$ LANGUAGE plpgsql;

/*Sign validation Ahora en exito devuelve llae de cifrado*/
CREATE OR REPLACE FUNCTION sp_crypto_validation(
  crypto VARCHAR(2000),
  ced VARCHAR(12)
) RETURNS VARCHAR AS $passed$
BEGIN
  IF EXISTS(SELECT per_id
            FROM persona
            WHERE per_sign_key = crypto
                  AND per_ced = ced)
  THEN
    RETURN (SELECT per_cipher_key FROM persona WHERE per_ced = ced AND per_sign_key = crypto);
  ELSE
    RETURN 'NO';
  END IF;
END;
$passed$ LANGUAGE plpgsql;

/*Is timestamp?*/
CREATE OR REPLACE FUNCTION is_timestamp(fecha VARCHAR) RETURNS BOOLEAN as $passed$
BEGIN
  PERFORM fecha::TIMESTAMP;
  RETURN TRUE;
EXCEPTION WHEN OTHERS THEN
  RETURN FALSE ;
end;
$passed$ LANGUAGE plpgsql;

/*New Sale*/
CREATE OR REPLACE FUNCTION sp_new_sale(
  scid INT,
  cedid VARCHAR(12),
  ord_id VARCHAR(50),
  fecha DATE,
  xmlobj XML
) RETURNS VARCHAR(2) AS $passed$
BEGIN
  INSERT INTO ventas(vt_scid, vt_cedid, vt_ordid, vt_fecha, vt_xml, vt_recibido)
  VALUES (scid, cedid, ord_id, fecha, xmlobj, XML '<?xml version="1.0"?><estado>PENDIENTE</estado>');
  RETURN 'OK';
END;
$passed$ LANGUAGE plpgsql;

/*Insert Ack -LM Apr 9 2017*/
CREATE OR REPLACE FUNCTION sp_insert_ack(
  ord_id VARCHAR(50),
  xmlobj XML
) RETURNS VARCHAR(2) AS $passed$
BEGIN
  UPDATE ventas
    SET vt_recibido = XML(xmlobj)
    WHERE vt_ordid = ord_id;
  RETURN 'OK';
END;
$passed$ LANGUAGE plpgsql;