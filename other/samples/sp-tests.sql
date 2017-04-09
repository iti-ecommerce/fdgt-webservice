/*Prueba*/
SELECT sp_fst_validation('01230456078901230456078901230456078901230456078900',
'012304560789',
'012304560789',
'2016-01-01T00:00:00-0600',
1);


/*Prueba*/
SELECT sp_crypto_validation('9C5BE39C6AE9B9160CA8FB5D20DAAE7D5769F20E',
                            '012304560789');

/*Prueba TMSTMP*/
SELECT is_timestamp('2016-01-01 00:00:00-0600');

/*Prueba*/
SELECT sp_new_sale(1,
                  '012304560789',
                  '01230456078901230456078901230456078901230456078900',
                  '2016-01-01T00:00:00-0600',
                  XML '<?xml version="1.0"?>
                      <order>ALO</order>');

/*Prueba ack*/
SELECT sp_insert_ack('01230456078901230456078901230456078901230456078900', XML 'hola');