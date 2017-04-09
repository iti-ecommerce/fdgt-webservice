/*CONTEXTO DB OBLIGATORIO--->*/
INSERT INTO tipoperso(tp_id, tp_descr)
VALUES (2, 'Juridica');

INSERT INTO tiporegimen(tr_id, tr_descrp)
    VALUES (1, 'Tradicional');
/*<---CONTEXTO DB OBLIGATORIO*/

/*ENTRADA TICCS--->*/
INSERT INTO persona(per_id,
                    per_nom,
                    per_ced,
                    per_tipoper,
                    per_crypto,
                    per_actividad,
                    per_tiporeg)
VALUES ('DGT-06-04-2017-7-55',
        'TICCS',
        '012304560789',
        2,
        '9C5BE39C6AE9B9160CA8FB5D20DAAE7D5769F20E',
        'Servicios de Tecnologias de la Informacion',
        1);

INSERT INTO sucursal(sc_cedid,
                     sc_scid,
                     sc_dir)
    VALUES ('012304560789',
            1,
            'San Ramón, Alajuela, Calle 1, Avenida 0, Campus Corporativo F. Ferrer, M2-F6');

INSERT INTO contacto(ct_idper,
                     ct_tipo,
                     ct_valor)
    VALUES ('DGT-06-04-2017-7-55',
            'mail',
            'admin@ticcs.fake');
/*<---ENTRADA TICCS*/